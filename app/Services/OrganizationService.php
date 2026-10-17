<?php

namespace App\Services;

use App\Models\Organization;
use App\Repositories\OrganizationRepository;
use Illuminate\Support\Collection;

class OrganizationService extends CoreService
{
    public function __construct(
        private readonly OrganizationRepository $organizationRepository
    ) {}

    public function all(): Collection
    {
        return $this->organizationRepository->allWithCounts();
    }

    public function create(array $data): Organization
    {
        return $this->organizationRepository->create($data);
    }

    public function findByIdentifierOrFail(string $identifier): Organization
    {
        return $this->organizationRepository->findByIdentifierOrFail($identifier);
    }

    public function findByIdentifierWithDetailsOrFail(string $identifier): Organization
    {
        $organization = $this->organizationRepository->findByIdentifierOrFail($identifier, ['users.roles', 'users.modules']);
        $organization->loadCount(['users', 'services', 'materials', 'periods']);

        return $organization;
    }

    public function update(Organization $organization, array $data): Organization
    {
        $organization->fill($data);
        $this->organizationRepository->save($organization);

        return $organization;
    }

    public function delete(Organization $organization): void
    {
        $this->organizationRepository->delete($organization);
    }
}
