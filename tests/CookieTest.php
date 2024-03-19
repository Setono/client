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
        $lastSeenAt = time();
        $firstSeenAt = $lastSeenAt - 1;
        $cookie = new Cookie('client_id', 2, $firstSeenAt, $lastSeenAt);

        self::assertSame('client_id', $cookie->clientId);
        self::assertSame(2, $cookie->version);
        self::assertSame($firstSeenAt, $cookie->firstSeenAt);
        self::assertSame($lastSeenAt, $cookie->lastSeenAt);
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

    /**
     * @test
     *
     * @dataProvider provideInvalidCookies
     */
    public function it_throws_exception_if_cookie_string_is_invalid(string $cookie): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Cookie::fromString($cookie);
    }

    /**
     * @return \Generator<array-key, array{string}>
     */
    public function provideInvalidCookies(): \Generator
    {
        yield [''];
        yield ['2.123.client_id'];
        yield ['one.123.123.client_id'];
        yield ['2.first_seen_at.123.client_id'];
        yield ['2.123.last_seen_at.client_id'];
    }
}
