<?php

namespace App\Presenters\Period;

use App\Models\PeriodEntry;
use App\Presenters\CorePresenter;

class PeriodEntryPresenter extends CorePresenter
{
    public function transform(PeriodEntry $entry): array
    {
        return [
            'identifier' => $entry->identifier,
            'quantity' => (float) $entry->quantity,
            'createdAt' => $entry->created_at,
            'updatedAt' => $entry->updated_at,
            'createdBy' => $entry->creator?->name,
            'service' => [
                'identifier' => $entry->service->identifier,
                'code' => $entry->service->code,
                'name' => $entry->service->name,
                'unit' => $entry->service->unit,
            ],
        ];
    }
}
