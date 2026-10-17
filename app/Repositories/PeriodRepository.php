<?php

namespace App\Repositories;

use App\Models\Period;
use Illuminate\Support\Collection;

class PeriodRepository extends CoreRepository
{
    public function __construct(Period $model)
    {
        parent::__construct($model);
    }

    public function create(array $data): Period
    {
        $period = new Period($data);
        $period->identifier = $this->generateIdentifier();
        $this->save($period);

        return $period;
    }

    public function findByIdentifierForOrganization(string $identifier, int $organizationId, array $with = []): ?Period
    {
        return $this->model
            ->with($with)
            ->where('identifier', $identifier)
            ->where('organization_id', $organizationId)
            ->first();
    }

    public function findOpenPeriod(int $organizationId, int $month, int $year): ?Period
    {
        return $this->model
            ->where('organization_id', $organizationId)
            ->where('month', $month)
            ->where('year', $year)
            ->first();
    }

    public function allForOrganization(int $organizationId): Collection
    {
        return $this->model
            ->where('organization_id', $organizationId)
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->get();
    }
}
