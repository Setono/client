<?php

declare(strict_types=1);

namespace Setono\Client;

use PHPUnit\Framework\TestCase;

final class MetadataTest extends TestCase
{
    protected function setUp(): void
    {
        require_once __DIR__ . '/StaticClock.php';
    }

    /**
     * @test
     */
    public function it_instantiates(): void
    {
        $metadata = new Metadata();

        self::assertCount(0, $metadata);
    }

    /**
     * @test
     */
    public function it_sets_and_gets_and_removes(): void
    {
        $now = time();
        StaticClock::setTime($now);

        $metadata = new Metadata();
        $metadata->set('foo', 'bar');
        $metadata->set('foo_expires', 'bar', 10);
        $metadata['bar'] = 'baz';

        self::assertCount(3, $metadata);
        self::assertSame('bar', $metadata->get('foo'));
        self::assertSame('bar', $metadata->get('foo_expires'));
        self::assertSame('baz', $metadata['bar']);
        self::assertTrue(isset($metadata['foo']));
        self::assertTrue(isset($metadata['foo_expires']));
        self::assertTrue(isset($metadata['bar']));
        self::assertSame([
            'foo' => 'bar',
            'foo_expires' => 'bar',
            '__expires' => [
                'foo_expires' => $now + 10,
            ],
            'bar' => 'baz',
        ], $metadata->toArray());

        StaticClock::setTime($now + 11);

        $metadata->remove('foo');
        unset($metadata['bar']);
        self::assertCount(0, $metadata);
        self::assertFalse($metadata->has('foo'));
        self::assertFalse($metadata->has('foo_expires'));
        self::assertFalse($metadata->has('bar'));
    }

    /**
     * @test
     */
    public function it_json_serializes(): void
    {
        $metadata = new Metadata(['foo' => 'bar', 'bar' => 'baz']);

        self::assertSame('{"foo":"bar","bar":"baz"}', json_encode($metadata));
    }

    /**
     * @test
     */
    public function it_json_serializes_with_expiring_items(): void
    {
        $expiresAt = time() + 10;

        $metadata = new Metadata(['foo' => 'bar', 'bar' => 'baz']);
        $metadata->set('expiring_key', 'expiring_value', 10);

        self::assertSame(
            sprintf('{"foo":"bar","bar":"baz","expiring_key":"expiring_value","__expires":{"expiring_key":%d}}', $expiresAt),
            json_encode($metadata),
        );
    }

    /**
     * @test
     */
    public function it_iterates(): void
    {
        $metadata = new Metadata();
        $metadata->set('foo', 'bar');

        foreach ($metadata as $key => $value) {
            self::assertSame('foo', $key);
            self::assertSame('bar', $value);
        }
    }

    /**
     * @test
     */
    public function it_iterates_without_expired_items(): void
    {
        $now = time();
        StaticClock::setTime($now);

        $metadata = new Metadata();
        $metadata->set('foo', 'bar');
        $metadata->set('expiring_key', 'expiring_value', 1);

        StaticClock::setTime($now + 10);

        foreach ($metadata as $key => $value) {
            self::assertSame('foo', $key);
            self::assertSame('bar', $value);
        }
    }

    /**
     * @test
     */
    public function it_does_not_count_expired_key(): void
    {
        $now = time();

        StaticClock::setTime($now);

        $metadata = new Metadata();
        $metadata->set('foo', 'bar'); // Should be included in the count
        $metadata->set('expiring_key1', 'expiring_value1', 5); // Should be included in the count
        $metadata->set('expiring_key2', 'expiring_value2', 10); // Should be included in the count
        $metadata->set('expiring_key3', 'expiring_value3', 100); // Should NOT be included in the count

        StaticClock::setTime($now + 10);

        self::assertCount(3, $metadata);
    }

    /**
     * @test
     */
    public function it_throws_exception_if_trying_to_set_reserved_key(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Metadata())->set('__expires', 'bar');
    }

    /**
     * @test
     */
    public function it_throws_exception_if_trying_to_set_key_with_zero_ttl(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Metadata())->set('foo', 'bar', 0);
    }

    /**
     * @test
     */
    public function it_throws_exception_if_trying_to_set_key_with_negative_ttl(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Metadata())->set('foo', 'bar', -1);
    }

    /**
     * @test
     */
    public function it_throws_exception_if_trying_to_get_missing_key(): void
    {
        $metadata = new Metadata();

        $this->expectException(\InvalidArgumentException::class);
        $metadata->get('foo');
    }

    /**
     * @test
     */
    public function it_throws_exception_if_trying_to_set_with_null_key(): void
    {
        $metadata = new Metadata();
        $this->expectException(\InvalidArgumentException::class);
        $metadata[] = 'test';
    }
}
