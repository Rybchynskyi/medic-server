<?php

namespace App\Services;

use App\Enums\RoleNames;
use App\Enums\UserStatuses;
use App\Models\User;
use App\Notifications\AccessGranted;
use App\Notifications\Invitation;
use App\Notifications\NewRegistrationRequest;
use App\Repositories\OrganizationRepository;
use App\Repositories\UsersRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

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

    /**
     * Without a password the caller is expected to send an invitation (see sendInvitation).
     */
    public function create(array $data): User
    {
        $user = $this->usersRepository->create([
            'organization_id' => $this->resolveOrganizationId($data['organizationIdentifier'] ?? null),
            'name' => $data['name'],
            'email' => $data['email'],
            'status' => UserStatuses::ACTIVE,
            // Unguessable placeholder until the invitation is accepted.
            'password' => $data['password'] ?? Str::random(40),
        ]);

        $user->syncRoles([$data['role'] ?? RoleNames::USER]);

        return $user;
    }

    /**
     * Self-registration creates a pending user who can't log in until an admin approves.
     * The organization is found by its EDRPOU code, or created if it isn't registered yet.
     */
    public function register(array $data): User
    {
        $user = DB::transaction(function () use ($data) {
            $organization = $this->organizationRepository->findOneBy(['edrpou' => $data['edrpou']])
                ?? $this->organizationRepository->create([
                    'name' => $data['organizationName'],
                    'edrpou' => $data['edrpou'],
                ]);

            $user = $this->usersRepository->create([
                'organization_id' => $organization->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'status' => UserStatuses::PENDING,
                'password' => $data['password'],
            ]);

            $user->syncRoles([RoleNames::USER]);
            $this->moduleService->enableDefaultsForUser($user);

            return $user;
        });

        $user->load('organization');
        foreach ($this->usersRepository->admins() as $admin) {
            $this->sendMail($admin, new NewRegistrationRequest($user));
        }

        return $user;
    }

    /**
     * @return bool whether the "access granted" email was sent
     */
    public function approve(User $user): bool
    {
        $user->status = UserStatuses::ACTIVE;
        $this->usersRepository->save($user);

        return $this->sendMail($user, new AccessGranted);
    }

    public function reject(User $user): void
    {
        $user->status = UserStatuses::REJECTED;
        $this->usersRepository->save($user);
    }

    /**
     * @return bool whether the invitation email was sent
     */
    public function sendInvitation(User $user): bool
    {
        $token = Password::broker('invites')->createToken($user);

        return $this->sendMail($user, new Invitation($token));
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

    /**
     * A mail server outage must not break the action that triggered the email.
     */
    private function sendMail(User $user, Notification $notification): bool
    {
        try {
            $user->notify($notification);

            return true;
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }

    private function resolveOrganizationId(?string $identifier): ?int
    {
        if (! $identifier) {
            return null;
        }

        return $this->organizationRepository->findByIdentifier($identifier)?->id;
    }
}
