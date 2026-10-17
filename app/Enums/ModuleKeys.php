<?php

namespace App\Enums;

class ModuleKeys extends CoreEnum
{
    public const MATERIALS_CALCULATOR = 'materials_calculator';

    public static function titles(): array
    {
        return [
            self::MATERIALS_CALCULATOR => 'Розрахунок витрат матеріалів',
        ];
    }
}
