<?php

namespace App\Presenters;

use App\Enums\OrganizationStatuses;
use App\Models\Organization;
use App\Notifications\ClientInvitation;

class OrganizationPresenter extends CorePresenter
{
    public function transform(Organization $organization): array
    {
        $status = $organization->clientStatus();

        return [
            'identifier' => $organization->identifier,
            'name' => $organization->name,
            'ourName' => $organization->our_name,
            'edrpou' => $organization->edrpou,
            'director' => $organization->director,
            'address' => $organization->address,
            'contactPerson' => $organization->contact_person,
            'contactPhone' => $organization->contact_phone,
            'contactEmail' => $organization->contact_email,
            'contracts' => $organization->contracts,
            'adminNote' => $organization->admin_note,
            'invitedAt' => $organization->invited_at,
            'registrationUrl' => $organization->edrpou ? ClientInvitation::registrationUrl($organization) : null,
            'status' => $status,
            'statusTitle' => OrganizationStatuses::getTitle($status),
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
