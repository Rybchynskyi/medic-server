<?php

namespace App\Repositories;

use App\Enums\UserStatuses;
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

    public function allWithCounts()
    {
        return $this->model
            ->withCount(['users', 'services', 'materials', 'periods'])
            ->withCount([
                'users as active_users_count' => fn ($query) => $query->where('status', UserStatuses::ACTIVE),
                'users as pending_users_count' => fn ($query) => $query->where('status', UserStatuses::PENDING),
            ])
            ->orderBy('name')
            ->get();
    }

    public function findByEdrpou(string $edrpou): ?Organization
    {
        return $this->model->where('edrpou', $edrpou)->first();
    }
}
