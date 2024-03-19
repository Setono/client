<?php

declare(strict_types=1);

namespace Setono\Client;

use PHPUnit\Framework\TestCase;

final class CookieTest extends TestCase
{
    /**
     * @test
     */
    public function it_instantiates(): void
    {
        $now = time();
        $cookie = new Cookie('client_id', 2, $now, $now);

        self::assertSame('client_id', $cookie->clientId);
        self::assertSame(2, $cookie->version);
        self::assertSame($now, $cookie->firstSeenAt);
        self::assertSame($now, $cookie->lastSeenAt);
    }

    /**
     * @test
     */
    public function it_stringifies(): void
    {
        $now = time();
        $cookie = new Cookie('client_id', 2, $now, $now);

        $expected = sprintf('2.%d.%d.client_id', $now, $now);

        self::assertSame($expected, $cookie->toString());
        self::assertSame($expected, (string) $cookie);
    }

    /**
     * @test
     */
    public function it_creates_v1_from_string(): void
    {
        $cookie = Cookie::fromString('client_id');

        self::assertSame('client_id', $cookie->clientId);
        self::assertSame(1, $cookie->version);
        self::assertLessThanOrEqual(time(), $cookie->firstSeenAt);
        self::assertLessThanOrEqual(time(), $cookie->lastSeenAt);
    }

    /**
     * @test
     */
    public function it_creates_v2_from_string(): void
    {
        $now = time();
        $cookie = Cookie::fromString(sprintf('2.%d.%d.client_id', $now, $now));

        self::assertSame('client_id', $cookie->clientId);
        self::assertSame(2, $cookie->version);
        self::assertSame($now, $cookie->firstSeenAt);
        self::assertSame($now, $cookie->lastSeenAt);
    }
}
