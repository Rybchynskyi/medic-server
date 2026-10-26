<?php

namespace App\Enums;

class UserStatuses extends CoreEnum
{
    public const PENDING = 'pending';

    public const ACTIVE = 'active';

    public const REJECTED = 'rejected';

    public static function titles(): array
    {
        return [
            self::PENDING => 'Очікує підтвердження',
            self::ACTIVE => 'Активний',
            self::REJECTED => 'Відхилено',
        ];
    }
}
