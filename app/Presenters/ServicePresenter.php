<?php

namespace App\Presenters;

use App\Models\Service;

class ServicePresenter extends CorePresenter
{
    public function transform(Service $service): array
    {
        return [
            'identifier' => $service->identifier,
            'code' => $service->code,
            'name' => $service->name,
            'unit' => $service->unit,
            'category' => $service->category,
        ];
    }
}
