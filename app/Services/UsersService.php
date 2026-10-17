<?php

namespace App\Services;

use App\Enums\RoleNames;
use App\Models\User;
use App\Repositories\OrganizationRepository;
use App\Repositories\UsersRepository;
use Illuminate\Database\Eloquent\Collection;

class UsersService extends CoreService
{
    public function __construct(
        private readonly UsersRepository $usersRepository,
        private readonly OrganizationRepository $organizationRepository,
        private readonly ModuleService $moduleService
    ) {}

    public function allWithRelations(): Collection
    {
        return $this->usersRepository->allWithRelations();
    }

    public function findByIdentifierOrFail(string $identifier): User
    {
        return $this->usersRepository->findByIdentifierOrFail($identifier, ['organization', 'roles', 'modules']);
    }

    public function create(array $data): User
    {
        $user = $this->usersRepository->create([
            'organization_id' => $this->resolveOrganizationId($data['organizationIdentifier'] ?? null),
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        $user->syncRoles([$data['role'] ?? RoleNames::USER]);

        return $user;
    }

    public function update(User $user, array $data): User
    {
        $user->fill([
            'name' => $data['name'] ?? $user->name,
            'email' => $data['email'] ?? $user->email,
        ]);

        if (array_key_exists('organizationIdentifier', $data)) {
            $user->organization_id = $this->resolveOrganizationId($data['organizationIdentifier']);
        }

        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }

        $this->usersRepository->save($user);

        if (array_key_exists('role', $data)) {
            $user->syncRoles([$data['role']]);
        }

        return $user;
    }

    public function delete(User $user): void
    {
        $this->usersRepository->delete($user);
    }

    public function updateOwnProfile(User $user, string $name): User
    {
        $user->name = $name;
        $this->usersRepository->save($user);

        return $user;
    }

    public function updateOwnPassword(User $user, string $password): void
    {
        $user->password = $password;
        $this->usersRepository->save($user);
    }

    public function toggleModule(User $user, string $moduleKey, bool $enabled): User
    {
        $this->moduleService->toggleForUser($user, $moduleKey, $enabled);

        return $user->refresh();
    }

    private function resolveOrganizationId(?string $identifier): ?int
    {
        if (! $identifier) {
            return null;
        }

        return $this->organizationRepository->findByIdentifier($identifier)?->id;
    }
}
