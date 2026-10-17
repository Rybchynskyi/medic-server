<?php

namespace App\Enums\Period;

use App\Enums\CoreEnum;

class PeriodStatuses extends CoreEnum
{
    public const OPEN = 'open';

    public const CLOSED = 'closed';

    public static function titles(): array
    {
        return [
            self::OPEN => 'Відкритий',
            self::CLOSED => 'Закритий',
        ];
    }
}
