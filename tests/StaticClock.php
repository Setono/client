<?php

declare(strict_types=1);

namespace Setono\Client;

/**
 * This file is loaded eagerly through the "files" autoloader (see autoload-dev in composer.json) so that the
 * Setono\Client\time() shadow below is defined before any unqualified time() call in src/ is first executed.
 * PHP caches that first binding per call site, so loading it lazily (e.g. in a test setUp) would let some call
 * sites bind to the global time() and ignore the clock.
 */
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
