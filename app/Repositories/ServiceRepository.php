<?php

namespace App\Repositories;

use App\Models\Service;
use Illuminate\Support\Collection;

class ServiceRepository extends CoreRepository
{
    public function __construct(Service $model)
    {
        parent::__construct($model);
    }

    public function create(array $data): Service
    {
        $service = new Service($data);
        $service->identifier = $this->generateIdentifier();
        $this->save($service);

        return $service;
    }

    public function findByIdentifierForOrganization(string $identifier, int $organizationId): ?Service
    {
        return $this->model
            ->where('identifier', $identifier)
            ->where('organization_id', $organizationId)
            ->first();
    }

    public function findByOrganizationAndCode(int $organizationId, string $code): ?Service
    {
        return $this->model
            ->where('organization_id', $organizationId)
            ->where('code', $code)
            ->first();
    }

    public function search(int $organizationId, ?string $search = null, int $limit = 20): Collection
    {
        return $this->model
            ->where('organization_id', $organizationId)
            ->when($search, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->orderBy('code')
            ->limit($limit)
            ->get();
    }

    public function allForOrganization(int $organizationId): Collection
    {
        return $this->model
            ->where('organization_id', $organizationId)
            ->orderBy('code')
            ->get();
    }

    public function findByIdentifiersForOrganization(array $identifiers, int $organizationId): Collection
    {
        return $this->model
            ->where('organization_id', $organizationId)
            ->whereIn('identifier', $identifiers)
            ->get();
    }
}
