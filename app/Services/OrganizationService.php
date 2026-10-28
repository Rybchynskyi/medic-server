<?php

namespace App\Services;

use App\Models\Organization;
use App\Notifications\ClientInvitation;
use App\Repositories\OrganizationRepository;
use App\Support\MailGuard;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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
        return $this->organizationRepository->create($this->toAttributes($data));
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
        $organization->fill($this->toAttributes($data));
        $this->organizationRepository->save($organization);

        return $organization;
    }

    public function delete(Organization $organization): void
    {
        $this->organizationRepository->delete($organization);
    }

    /**
     * Emails the contact person an offer to apply for access and marks the organization as invited.
     */
    public function invite(Organization $organization): Organization
    {
        if (blank($organization->contact_email)) {
            throw ValidationException::withMessages([
                'contactEmail' => 'У установи не вказано email контактної особи. Додайте його в контактах або скопіюйте посилання й надішліть вручну.',
            ]);
        }

        if (! MailGuard::allows($organization->contact_email)) {
            throw ValidationException::withMessages([
                'contactEmail' => "Тестовий режим пошти: лист на {$organization->contact_email} не надіслано. "
                    .'Дозволено лише: '.MailGuard::allowedList().'.',
            ]);
        }

        try {
            Notification::route('mail', $organization->contact_email)->notify(new ClientInvitation($organization));
        } catch (\Throwable $e) {
            report($e);

            throw ValidationException::withMessages([
                'contactEmail' => 'Не вдалося надіслати лист. Перевірте налаштування пошти або спробуйте пізніше.',
            ]);
        }

        $organization->invited_at = now();
        $this->organizationRepository->save($organization);

        return $organization;
    }

    /**
     * Request fields are camelCase (ourName, contactEmail…); model attributes are snake_case.
     */
    private function toAttributes(array $data): array
    {
        $attributes = [];
        foreach ($data as $key => $value) {
            $attributes[Str::snake($key)] = is_string($value) ? (trim($value) === '' ? null : trim($value)) : $value;
        }

        return $attributes;
    }
}
