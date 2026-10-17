<?php

namespace App\Presenters;

use App\Models\Organization;

class OrganizationPresenter extends CorePresenter
{
    public function transform(Organization $organization): array
    {
        return [
            'identifier' => $organization->identifier,
            'name' => $organization->name,
            'createdAt' => $organization->created_at,
        ];
    }
}
