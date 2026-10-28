<?php

namespace App\Presenters;

use App\Models\ServiceMaterialNorm;

class ServiceMaterialNormPresenter extends CorePresenter
{
    public function transform(ServiceMaterialNorm $norm): array
    {
        return [
            'material' => $norm->material->name,
            'unit' => $norm->material->unit,
            'quantityPerService' => (float) $norm->quantity_per_service,
        ];
    }
}
