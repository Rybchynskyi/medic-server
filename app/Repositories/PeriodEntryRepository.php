<?php

namespace App\Repositories;

use App\Models\PeriodEntry;
use Illuminate\Support\Collection;

class PeriodEntryRepository extends CoreRepository
{
    public function __construct(PeriodEntry $model)
    {
        parent::__construct($model);
    }

    public function create(array $data): PeriodEntry
    {
        $entry = new PeriodEntry($data);
        $entry->identifier = $this->generateIdentifier();
        $this->save($entry);

        return $entry;
    }

    public function allForPeriod(int $periodId): Collection
    {
        return $this->model->with('service')->where('period_id', $periodId)->orderBy('id')->get();
    }

    public function findByIdentifierForPeriod(string $identifier, int $periodId): ?PeriodEntry
    {
        return $this->model
            ->where('identifier', $identifier)
            ->where('period_id', $periodId)
            ->first();
    }
}
