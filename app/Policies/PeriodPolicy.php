<?php

namespace App\Policies;

use App\Models\Period;
use App\Models\User;

class PeriodPolicy extends CorePolicy
{
    public function hasAccess(User $user, Period $period): bool
    {
        return $user->organization_id === $period->organization_id;
    }

    public function canEdit(User $user, Period $period): bool
    {
        return $this->hasAccess($user, $period) && $period->isOpen();
    }

    public function canReopen(User $user, Period $period): bool
    {
        return $this->hasAccess($user, $period) && ! $period->isOpen();
    }
}
