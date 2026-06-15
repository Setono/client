<?php

declare(strict_types=1);

namespace Setono\Client;

use PHPUnit\Framework\TestCase;

final class MetadataTest extends TestCase
{
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
            'bar' => 'baz',
            '__expires' => [
                'foo_expires' => $now + 10,
            ],
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

    /**
     * @test
     */
    public function it_reconstructs_from_array_including_expiry(): void
    {
        $now = time();
        StaticClock::setTime($now);

        $metadata = new Metadata();
        $metadata->set('persistent', 'value');
        $metadata->set('expiring', 'value', 100);

        $reconstructed = new Metadata($metadata->toArray());

        self::assertSame('value', $reconstructed->get('persistent'));
        self::assertSame('value', $reconstructed->get('expiring'));
        self::assertSame($metadata->toArray(), $reconstructed->toArray());

        // the restored expiry timestamp is still honored
        StaticClock::setTime($now + 101);
        self::assertFalse($reconstructed->has('expiring'));
        self::assertTrue($reconstructed->has('persistent'));
    }

    /**
     * @test
     */
    public function it_expires_a_key_when_calling_has(): void
    {
        $now = time();
        StaticClock::setTime($now);

        $metadata = new Metadata();
        $metadata->set('expiring', 'value', 10);
        $metadata->set('keep', 'value');

        self::assertTrue($metadata->has('expiring'));

        StaticClock::setTime($now + 11);

        self::assertFalse($metadata->has('expiring'));
        // jsonSerialize() does not prune, so this proves has() physically removed the expired key and its bookkeeping
        self::assertSame(['keep' => 'value'], $metadata->jsonSerialize());
    }

    /**
     * @test
     */
    public function it_keeps_a_key_until_its_expiry_time_has_passed(): void
    {
        $now = time();
        StaticClock::setTime($now);

        $metadata = new Metadata();
        $metadata->set('expiring', 'value', 10);

        // exactly at the expiry timestamp the key is still present (it expires strictly after)
        StaticClock::setTime($now + 10);
        self::assertTrue($metadata->has('expiring'));

        // one second later it is gone
        StaticClock::setTime($now + 11);
        self::assertFalse($metadata->has('expiring'));
    }

    /**
     * @test
     */
    public function it_prunes_expired_keys_in_to_array(): void
    {
        $now = time();
        StaticClock::setTime($now);

        $metadata = new Metadata();
        $metadata->set('expiring', 'value', 10);
        $metadata->set('keep', 'value');

        StaticClock::setTime($now + 11);

        self::assertSame(['keep' => 'value'], $metadata->toArray());
    }

    /**
     * @test
     */
    public function it_ignores_malformed_expiry_metadata_when_reconstructing(): void
    {
        $metadata = new Metadata([
            'foo' => 'bar',
            Metadata::EXPIRES_KEY => ['foo' => 'not-an-integer'],
        ]);

        self::assertSame(['foo' => 'bar'], $metadata->toArray());
    }

    /**
     * @test
     */
    public function it_throws_exception_if_trying_to_get_an_expired_key(): void
    {
        $now = time();
        StaticClock::setTime($now);

        $metadata = new Metadata();
        $metadata->set('expiring', 'value', 10);

        StaticClock::setTime($now + 11);

        $this->expectException(\InvalidArgumentException::class);
        $metadata->get('expiring');
    }

    /**
     * @test
     */
    public function it_drops_the_expires_container_when_the_last_expiring_key_is_removed(): void
    {
        $metadata = new Metadata();
        $metadata->set('foo', 'bar');
        $metadata->set('expiring', 'value', 100);

        self::assertArrayHasKey(Metadata::EXPIRES_KEY, $metadata->toArray());

        $metadata->remove('expiring');

        self::assertSame(['foo' => 'bar'], $metadata->toArray());
    }
}
