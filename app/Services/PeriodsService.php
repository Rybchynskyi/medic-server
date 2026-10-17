<?php

namespace App\Services;

use App\Enums\Period\PeriodStatuses;
use App\Models\Organization;
use App\Models\Period;
use App\Models\PeriodEntry;
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

    public function close(Period $period): Period
    {
        DB::transaction(function () use ($period) {
            $entries = $this->periodEntryRepository->allForPeriod($period->id);

            $serviceIds = $entries->pluck('service_id')->unique()->values()->all();
            $norms = $this->serviceMaterialNormRepository->findByServiceIds($serviceIds);
            $normsByService = $norms->groupBy('service_id');

            $totals = [];
            foreach ($entries as $entry) {
                foreach ($normsByService->get($entry->service_id, []) as $norm) {
                    $totals[$norm->material_id] = ($totals[$norm->material_id] ?? 0)
                        + ((float) $entry->quantity * (float) $norm->quantity_per_service);
                }
            }

            $rows = [];
            $now = now();
            foreach ($totals as $materialId => $totalQuantity) {
                $rows[] = [
                    'period_id' => $period->id,
                    'material_id' => $materialId,
                    'total_quantity' => round($totalQuantity, 4),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

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
