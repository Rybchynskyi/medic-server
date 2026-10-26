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
            'edrpou' => $organization->edrpou,
            'createdAt' => $organization->created_at,
            'usersCount' => $organization->users_count ?? null,
            'servicesCount' => $organization->services_count ?? null,
            'materialsCount' => $organization->materials_count ?? null,
            'periodsCount' => $organization->periods_count ?? null,
        ];
    }

    public function includeUsers(Organization $organization): array
    {
        return $this->includeMany($organization->users, UserForAdminPresenter::class);
    }
}
