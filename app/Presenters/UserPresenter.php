<?php

namespace App\Presenters;

use App\Models\User;

class UserPresenter extends CorePresenter
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
            'modules' => $user->modules
                ->filter(fn ($module) => (bool) $module->pivot->enabled)
                ->pluck('key'),
        ];
    }
}
