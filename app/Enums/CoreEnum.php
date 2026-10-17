<?php

namespace App\Enums;

abstract class CoreEnum
{
    public static function getConstants(): array
    {
        return (new \ReflectionClass(static::class))->getConstants();
    }

    public static function getKeys(): array
    {
        return array_keys(self::getConstants());
    }

    public static function getValues(): array
    {
        return array_values(self::getConstants());
    }

    public static function getTitle($value)
    {
        return static::titles()[$value] ?? $value;
    }
}
