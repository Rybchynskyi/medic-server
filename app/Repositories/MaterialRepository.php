<?php

namespace App\Repositories;

use App\Models\Material;

class MaterialRepository extends CoreRepository
{
    public function __construct(Material $model)
    {
        parent::__construct($model);
    }

    public function create(array $data): Material
    {
        $material = new Material($data);
        $material->identifier = $this->generateIdentifier();
        $this->save($material);

        return $material;
    }

    public function findByOrganizationNameAndUnit(int $organizationId, string $name, string $unit): ?Material
    {
        return $this->model
            ->where('organization_id', $organizationId)
            ->where('name', $name)
            ->where('unit', $unit)
            ->first();
    }
}
