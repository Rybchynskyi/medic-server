<?php

namespace App\Repositories;

use App\Models\ServiceMaterialNorm;

class ServiceMaterialNormRepository extends CoreRepository
{
    public function __construct(ServiceMaterialNorm $model)
    {
        parent::__construct($model);
    }

    public function updateOrCreate(int $organizationId, int $serviceId, int $materialId, float $quantityPerService): ServiceMaterialNorm
    {
        return $this->model->updateOrCreate(
            ['service_id' => $serviceId, 'material_id' => $materialId],
            ['organization_id' => $organizationId, 'quantity_per_service' => $quantityPerService]
        );
    }

    public function findByServiceIds(array $serviceIds)
    {
        return $this->model->whereIn('service_id', $serviceIds)->get();
    }
}
