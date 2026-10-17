<?php

namespace App\Presenters\Period;

use App\Enums\Period\PeriodStatuses;
use App\Models\Period;
use App\Presenters\CorePresenter;

class PeriodPresenter extends CorePresenter
{
    public function transform(Period $period): array
    {
        return [
            'identifier' => $period->identifier,
            'month' => $period->month,
            'year' => $period->year,
            'status' => $period->status,
            'statusTitle' => PeriodStatuses::getTitle($period->status),
            'closedAt' => $period->closed_at,
        ];
    }

    public function includeEntries(Period $period): array
    {
        return $this->includeMany($period->entries, PeriodEntryPresenter::class);
    }

    public function includeMaterialTotals(Period $period): array
    {
        return $this->includeMany($period->materialTotals, PeriodMaterialTotalPresenter::class);
    }
}
