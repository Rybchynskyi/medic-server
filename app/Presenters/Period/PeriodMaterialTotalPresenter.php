<?php

namespace App\Presenters\Period;

use App\Models\PeriodMaterialTotal;
use App\Presenters\CorePresenter;

class PeriodMaterialTotalPresenter extends CorePresenter
{
    public function transform(PeriodMaterialTotal $total): array
    {
        return [
            'material' => [
                'identifier' => $total->material->identifier,
                'name' => $total->material->name,
                'unit' => $total->material->unit,
            ],
            'totalQuantity' => (float) $total->total_quantity,
        ];
    }
}
