<?php

declare(strict_types=1);

namespace Setono\Client;

final class StaticClock
{
    private static ?int $time = null;

    public static function setTime(int $time): void
    {
        self::$time = $time;
    }

    public static function time(): int
    {
        return self::$time ?? \time();
    }
}

function time(): int
{
    return StaticClock::time();
}
