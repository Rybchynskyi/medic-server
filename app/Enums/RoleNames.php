<?php

namespace App\Enums;

class RoleNames extends CoreEnum
{
    public const ADMIN = 'admin';

    public const USER = 'user';

    public static function titles(): array
    {
        return [
            self::ADMIN => 'Адміністратор',
            self::USER => 'Користувач',
        ];
    }
}
