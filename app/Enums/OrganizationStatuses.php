<?php

namespace App\Enums;

/**
 * Derived client status of an organization (not stored): from its users and invited_at.
 */
class OrganizationStatuses extends CoreEnum
{
    public const NOT_INVITED = 'not_invited';

    public const INVITED = 'invited';

    public const APPLIED = 'applied';

    public const ACTIVE = 'active';

    public static function titles(): array
    {
        return [
            self::NOT_INVITED => 'Не запрошено',
            self::INVITED => 'Запрошено',
            self::APPLIED => 'Подав заявку',
            self::ACTIVE => 'Активний',
        ];
    }
}
