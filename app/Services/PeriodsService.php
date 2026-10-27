<?php

namespace App\Services;

use App\Enums\Period\PeriodStatuses;
use App\Models\Organization;
use App\Models\Period;
use App\Models\PeriodEntry;
use App\Models\PeriodMaterialTotal;
use App\Models\User;
use App\Repositories\PeriodEntryRepository;
use App\Repositories\PeriodMaterialTotalRepository;
use App\Repositories\PeriodRepository;
use App\Repositories\ServiceMaterialNormRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PeriodsService extends CoreService
{
    public function __construct(
        private readonly PeriodRepository $periodRepository,
        private readonly PeriodEntryRepository $periodEntryRepository,
        private readonly PeriodMaterialTotalRepository $periodMaterialTotalRepository,
        private readonly ServiceMaterialNormRepository $serviceMaterialNormRepository
    ) {}

    public function allForOrganization(Organization $organization): Collection
    {
        return $this->periodRepository->allForOrganization($organization->id);
    }

    public function findByIdentifierForOrganizationOrFail(string $identifier, Organization $organization, array $with = []): Period
    {
        $period = $this->periodRepository->findByIdentifierForOrganization($identifier, $organization->id, $with);

        if (! $period) {
            abort(404);
        }

        return $period;
    }

    public function open(Organization $organization, User $user, int $month, int $year): Period
    {
        if ($this->periodRepository->findOpenPeriod($organization->id, $month, $year)) {
            throw ValidationException::withMessages([
                'period' => 'Період за цей місяць і рік уже існує.',
            ]);
        }

        return $this->periodRepository->create([
            'organization_id' => $organization->id,
            'created_by' => $user->id,
            'month' => $month,
            'year' => $year,
            'status' => PeriodStatuses::OPEN,
        ]);
    }

    public function addEntry(Period $period, int $serviceId, float $quantity): PeriodEntry
    {
        return $this->periodEntryRepository->create([
            'period_id' => $period->id,
            'service_id' => $serviceId,
            'quantity' => $quantity,
        ]);
    }

    public function updateEntry(PeriodEntry $entry, float $quantity): PeriodEntry
    {
        $entry->quantity = $quantity;
        $this->periodEntryRepository->save($entry);

        return $entry;
    }

    public function deleteEntry(PeriodEntry $entry): void
    {
        $this->periodEntryRepository->delete($entry);
    }

    /**
     * Sets the quantity of each given service for the period: one entry per service, removed when quantity is 0.
     *
     * @param  array<int, float>  $quantitiesByServiceId
     */
    public function syncEntries(Period $period, array $quantitiesByServiceId): void
    {
        DB::transaction(function () use ($period, $quantitiesByServiceId) {
            $this->periodEntryRepository->deleteForPeriodAndServices($period->id, array_keys($quantitiesByServiceId));

            foreach ($quantitiesByServiceId as $serviceId => $quantity) {
                if ($quantity > 0) {
                    $this->addEntry($period, $serviceId, $quantity);
                }
            }
        });
    }

    /**
     * Stored totals for a closed period; for an open one they are calculated from the current entries.
     */
    public function materialTotalsFor(Period $period): Collection
    {
        if (! $period->isOpen()) {
            return $this->periodMaterialTotalRepository->allForPeriod($period->id);
        }

        return $this->calculateTotals($period);
    }

    /**
     * Sums entry quantity × norm per material. Returns unsaved PeriodMaterialTotal models with the material loaded.
     */
    public function calculateTotals(Period $period): Collection
    {
        $entries = $this->periodEntryRepository->allForPeriod($period->id);

        $serviceIds = $entries->pluck('service_id')->unique()->values()->all();
        $normsByService = $this->serviceMaterialNormRepository->findByServiceIds($serviceIds)->groupBy('service_id');

        $totals = [];
        $materials = [];
        foreach ($entries as $entry) {
            foreach ($normsByService->get($entry->service_id, []) as $norm) {
                $totals[$norm->material_id] = ($totals[$norm->material_id] ?? 0)
                    + ((float) $entry->quantity * (float) $norm->quantity_per_service);
                $materials[$norm->material_id] = $norm->material;
            }
        }

        return collect($totals)
            ->map(function ($totalQuantity, $materialId) use ($period, $materials) {
                $total = new PeriodMaterialTotal([
                    'period_id' => $period->id,
                    'material_id' => $materialId,
                    'total_quantity' => round($totalQuantity, 4),
                ]);
                $total->setRelation('material', $materials[$materialId]);

                return $total;
            })
            ->sortBy(fn (PeriodMaterialTotal $total) => mb_strtolower($total->material->name))
            ->values();
    }

    public function close(Period $period): Period
    {
        DB::transaction(function () use ($period) {
            $now = now();
            $rows = $this->calculateTotals($period)
                ->map(fn (PeriodMaterialTotal $total) => [
                    'period_id' => $period->id,
                    'material_id' => $total->material_id,
                    'total_quantity' => $total->total_quantity,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->all();

            if (! empty($rows)) {
                $this->periodMaterialTotalRepository->createMany($rows);
            }

            $period->status = PeriodStatuses::CLOSED;
            $period->closed_at = $now;
            $this->periodRepository->save($period);
        });

        return $period->refresh();
    }
}
