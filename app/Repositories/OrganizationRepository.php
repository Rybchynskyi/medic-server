<?php

namespace App\Repositories;

use App\Models\Organization;

class OrganizationRepository extends CoreRepository
{
    public function __construct(Organization $model)
    {
        parent::__construct($model);
    }

    public function create(array $data): Organization
    {
        $organization = new Organization($data);
        $organization->identifier = $this->generateIdentifier();
        $this->save($organization);

        return $organization;
    }

    public function all()
    {
        return $this->model->orderBy('name')->get();
    }
}
