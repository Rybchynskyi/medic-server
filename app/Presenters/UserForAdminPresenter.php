<?php

namespace App\Presenters;

use App\Models\User;

class UserForAdminPresenter extends CorePresenter
{
    public function transform(User $user): array
    {
        return [
            'identifier' => $user->identifier,
            'name' => $user->name,
            'email' => $user->email,
            'organization' => $user->organization ? [
                'identifier' => $user->organization->identifier,
                'name' => $user->organization->name,
            ] : null,
            'roles' => $user->roles->pluck('name'),
            'modules' => $user->modules->map(fn ($module) => [
                'key' => $module->key,
                'name' => $module->name,
                'enabled' => (bool) $module->pivot->enabled,
            ]),
            'createdAt' => $user->created_at,
        ];
    }
}
