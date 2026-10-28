<?php

namespace App\Models;

use App\Enums\OrganizationStatuses;
use App\Enums\UserStatuses;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    /** Contact and client-base details an admin can edit; the clients import only fills them when empty. */
    public const CLIENT_DETAIL_FIELDS = [
        'our_name',
        'director',
        'address',
        'contact_person',
        'contact_phone',
        'contact_email',
        'contracts',
        'admin_note',
    ];

    protected $fillable = [
        'identifier',
        'name',
        'edrpou',
        ...self::CLIENT_DETAIL_FIELDS,
    ];

    protected function casts(): array
    {
        return [
            'invited_at' => 'datetime',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class);
    }

    public function periods(): HasMany
    {
        return $this->hasMany(Period::class);
    }

    /**
     * Expects active_users_count / pending_users_count to be loaded (see OrganizationRepository).
     */
    public function clientStatus(): string
    {
        $activeUsers = $this->active_users_count
            ?? $this->users()->where('status', UserStatuses::ACTIVE)->count();
        if ($activeUsers > 0) {
            return OrganizationStatuses::ACTIVE;
        }

        $pendingUsers = $this->pending_users_count
            ?? $this->users()->where('status', UserStatuses::PENDING)->count();
        if ($pendingUsers > 0) {
            return OrganizationStatuses::APPLIED;
        }

        return $this->invited_at ? OrganizationStatuses::INVITED : OrganizationStatuses::NOT_INVITED;
    }
}
