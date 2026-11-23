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

    /**
     * @test
     *
     * @dataProvider provideSetsWithoutATtlOnAKeyWithAnExpiry
     *
     * @param array<string, mixed> $data
     * @param \Closure(Metadata): void $set
     */
    public function it_clears_the_expiry_when_setting_without_a_ttl(array $data, \Closure $set): void
    {
        StaticClock::setTime(1_000);

        $metadata = new Metadata($data);
        $set($metadata);

        StaticClock::setTime(1_000_000);

        self::assertTrue($metadata->has('foo'));
        self::assertSame('baz', $metadata->get('foo'));
        self::assertSame(['foo' => 'baz'], $metadata->toArray());
    }

    /**
     * @test
     */
    public function it_has_not_changed_when_constructed(): void
    {
        StaticClock::setTime(1_000);

        self::assertFalse((new Metadata())->hasChanged());
        self::assertFalse((new Metadata([
            'foo' => 'bar',
            'expired' => 'value',
            Metadata::EXPIRES_KEY => ['expired' => 900, 'malformed' => 'not-an-integer'],
        ]))->hasChanged());
    }

    /**
     * @test
     */
    public function it_changes_when_setting_a_new_key(): void
    {
        $metadata = new Metadata(['foo' => 'bar']);
        $metadata->set('baz', 'qux');

        self::assertTrue($metadata->hasChanged());
    }

    /**
     * @test
     */
    public function it_changes_when_setting_a_different_value(): void
    {
        $metadata = new Metadata(['foo' => '1']);
        $metadata->set('foo', 1);

        self::assertTrue($metadata->hasChanged());
        self::assertSame(1, $metadata->get('foo'));
    }

    /**
     * @test
     */
    public function it_changes_when_a_ttl_adds_an_expiry(): void
    {
        StaticClock::setTime(1_000);

        $metadata = new Metadata(['foo' => 'bar']);
        $metadata->set('foo', 'bar', 60);

        self::assertTrue($metadata->hasChanged());
        self::assertSame(['foo' => 'bar', Metadata::EXPIRES_KEY => ['foo' => 1_060]], $metadata->toArray());
    }

    /**
     * @test
     */
    public function it_changes_when_a_ttl_moves_the_expiry(): void
    {
        StaticClock::setTime(1_000);

        $metadata = new Metadata(['foo' => 'bar', Metadata::EXPIRES_KEY => ['foo' => 1_060]]);
        $metadata->set('foo', 'bar', 61);

        self::assertTrue($metadata->hasChanged());
        self::assertSame(['foo' => 'bar', Metadata::EXPIRES_KEY => ['foo' => 1_061]], $metadata->toArray());
    }

    /**
     * @test
     */
    public function it_does_not_change_when_setting_the_same_value_without_a_ttl_on_a_key_without_an_expiry(): void
    {
        StaticClock::setTime(1_000);

        $metadata = new Metadata([
            'foo' => ['a' => 1],
            'expiring' => 'value',
            Metadata::EXPIRES_KEY => ['expiring' => 1_060],
        ]);
        $metadata->set('foo', ['a' => 1]);

        self::assertFalse($metadata->hasChanged());
        self::assertSame([
            'foo' => ['a' => 1],
            'expiring' => 'value',
            Metadata::EXPIRES_KEY => ['expiring' => 1_060],
        ], $metadata->toArray());
    }

    /**
     * @test
     *
     * @dataProvider provideLiveAndPassedExpiries
     */
    public function it_changes_when_setting_the_same_value_without_a_ttl_clears_an_expiry(int $expiresAt): void
    {
        StaticClock::setTime(1_000);

        $metadata = new Metadata(['foo' => 'bar', Metadata::EXPIRES_KEY => ['foo' => $expiresAt]]);
        $metadata->set('foo', 'bar');

        self::assertTrue($metadata->hasChanged());
        self::assertSame(['foo' => 'bar'], $metadata->jsonSerialize());
    }

    /**
     * @test
     */
    public function it_does_not_change_when_setting_the_same_value_with_a_ttl_giving_the_same_expiry(): void
    {
        StaticClock::setTime(1_000);

        $metadata = new Metadata(['foo' => 'bar', Metadata::EXPIRES_KEY => ['foo' => 1_060]]);
        $metadata->set('foo', 'bar', 60);

        self::assertFalse($metadata->hasChanged());
    }

    /**
     * @test
     */
    public function it_changes_when_removing_an_existing_key(): void
    {
        $metadata = new Metadata(['foo' => 'bar']);
        $metadata->remove('foo');

        self::assertTrue($metadata->hasChanged());
    }

    /**
     * @test
     */
    public function it_changes_when_unsetting_an_existing_key(): void
    {
        $metadata = new Metadata(['foo' => 'bar']);
        unset($metadata['foo']);

        self::assertTrue($metadata->hasChanged());
    }

    /**
     * @test
     */
    public function it_does_not_change_when_removing_or_unsetting_a_missing_key(): void
    {
        $metadata = new Metadata(['foo' => 'bar']);
        $metadata->remove('missing');
        unset($metadata['missing']);

        self::assertFalse($metadata->hasChanged());
    }

    /**
     * @test
     *
     * @dataProvider provideCallsThatDropAnExpiredKey
     *
     * @param \Closure(Metadata): mixed $call
     */
    public function it_changes_when_a_call_drops_an_expired_key(\Closure $call): void
    {
        StaticClock::setTime(1_000);

        $metadata = new Metadata([
            'live' => 'value',
            'expired' => 'value',
            Metadata::EXPIRES_KEY => ['expired' => 999],
        ]);

        $call($metadata);

        self::assertTrue($metadata->hasChanged());
        // jsonSerialize() does not prune, so this proves the call dropped the expired key
        self::assertSame(['live' => 'value'], $metadata->jsonSerialize());
    }

    /**
     * Released versions of setono/client-bundle subclass Metadata and override remove() to notice dropped keys
     *
     * @test
     *
     * @dataProvider provideCallsThatDropAnExpiredKey
     *
     * @param \Closure(Metadata): mixed $call
     */
    public function it_drops_an_expired_key_through_remove(\Closure $call): void
    {
        StaticClock::setTime(1_000);

        $metadata = new class(['live' => 'value', 'expired' => 'value', Metadata::EXPIRES_KEY => ['expired' => 999]]) extends Metadata {
            /** @var list<string> */
            public array $removed = [];

            public function remove(string $key): void
            {
                $this->removed[] = $key;

                parent::remove($key);
            }
        };

        $call($metadata);

        self::assertSame(['expired'], $metadata->removed);
    }

    /**
     * @test
     */
    public function it_changes_when_pruning_drops_an_expiry_without_a_value(): void
    {
        StaticClock::setTime(1_000);

        $metadata = new Metadata(['live' => 'value', Metadata::EXPIRES_KEY => ['orphan' => 999]]);

        self::assertCount(1, $metadata);
        self::assertTrue($metadata->hasChanged());
        self::assertSame(['live' => 'value'], $metadata->jsonSerialize());
    }

    /**
     * @test
     */
    public function it_does_not_change_when_reading_live_keys_or_json_serializing(): void
    {
        StaticClock::setTime(1_000);

        $metadata = new Metadata([
            'live' => 'value',
            'expiring' => 'value',
            'expired' => 'value',
            Metadata::EXPIRES_KEY => ['expiring' => 1_000, 'expired' => 999],
        ]);

        self::assertTrue($metadata->has('live'));
        self::assertSame('value', $metadata->get('expiring'));
        self::assertTrue(isset($metadata['live']));
        self::assertSame('value', $metadata['live']);
        self::assertArrayHasKey('expired', $metadata->jsonSerialize());

        self::assertFalse($metadata->hasChanged());
    }

    /**
     * @test
     */
    public function it_does_not_change_when_counting_iterating_or_exporting_without_expired_keys(): void
    {
        StaticClock::setTime(1_000);

        $data = ['live' => 'value', 'expiring' => 'value', Metadata::EXPIRES_KEY => ['expiring' => 1_000]];
        $metadata = new Metadata($data);

        self::assertCount(2, $metadata);
        self::assertSame(['live' => 'value', 'expiring' => 'value'], iterator_to_array($metadata));
        self::assertSame($data, $metadata->toArray());

        self::assertFalse($metadata->hasChanged());
    }

    /**
     * @test
     */
    public function it_stays_changed_when_a_value_is_set_back(): void
    {
        $metadata = new Metadata(['foo' => 'bar']);
        $metadata->set('foo', 'baz');
        $metadata->set('foo', 'bar');

        self::assertSame(['foo' => 'bar'], $metadata->toArray());
        self::assertTrue($metadata->hasChanged());
    }

    /**
     * @test
     *
     * @dataProvider provideRejectedCalls
     *
     * @param \Closure(Metadata): void $call
     */
    public function it_leaves_the_metadata_as_it_was_when_a_call_is_rejected(\Closure $call): void
    {
        $metadata = new Metadata(['foo' => 'bar']);

        self::assertThrowsInvalidArgument(static function () use ($call, $metadata): void {
            $call($metadata);
        });
        self::assertFalse($metadata->has('a'));
        self::assertSame(['foo' => 'bar'], $metadata->toArray());
        self::assertFalse($metadata->hasChanged());
    }

    /**
     * @return iterable<string, array{\Closure(Metadata): void}>
     */
    public static function provideRejectedCalls(): iterable
    {
        yield 'set() with a TTL of 0' => [static function (Metadata $metadata): void {
            $metadata->set('a', 1, 0);
        }];
        yield 'set() with a TTL of -1' => [static function (Metadata $metadata): void {
            $metadata->set('a', 1, -1);
        }];
        yield 'set() of the reserved key' => [static function (Metadata $metadata): void {
            $metadata->set(Metadata::EXPIRES_KEY, ['a' => 1]);
        }];
        yield 'offsetSet() with a null offset' => [static function (Metadata $metadata): void {
            $metadata[] = 1;
        }];
    }

    /**
     * @return iterable<string, array{array<string, mixed>, \Closure(Metadata): void}>
     */
    public static function provideSetsWithoutATtlOnAKeyWithAnExpiry(): iterable
    {
        $set = static function (Metadata $metadata): void {
            $metadata->set('foo', 'baz');
        };

        yield 'set() on a key whose expiry has passed' => [
            ['foo' => 'bar', Metadata::EXPIRES_KEY => ['foo' => 900]],
            $set,
        ];
        yield 'set() on a key with a live expiry' => [
            ['foo' => 'bar', Metadata::EXPIRES_KEY => ['foo' => 1_060]],
            $set,
        ];
        yield 'set() on a key that only has a restored expiry' => [
            [Metadata::EXPIRES_KEY => ['foo' => 1_060]],
            $set,
        ];
        $offsetSet = static function (Metadata $metadata): void {
            $metadata['foo'] = 'baz';
        };

        yield 'offsetSet() on a key whose expiry has passed' => [
            ['foo' => 'bar', Metadata::EXPIRES_KEY => ['foo' => 900]],
            $offsetSet,
        ];
        yield 'offsetSet() on a key with a live expiry' => [
            ['foo' => 'bar', Metadata::EXPIRES_KEY => ['foo' => 1_060]],
            $offsetSet,
        ];
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function provideLiveAndPassedExpiries(): iterable
    {
        yield 'a live expiry' => [1_060];
        yield 'an expiry that has passed' => [900];
    }

    /**
     * @return iterable<string, array{\Closure(Metadata): mixed}>
     */
    public static function provideCallsThatDropAnExpiredKey(): iterable
    {
        yield 'has()' => [static fn (Metadata $metadata): bool => $metadata->has('expired')];
        yield 'get()' => [static function (Metadata $metadata): void {
            self::assertThrowsInvalidArgument(static fn (): mixed => $metadata->get('expired'));
        }];
        yield 'offsetExists()' => [static fn (Metadata $metadata): bool => isset($metadata['expired'])];
        yield 'offsetGet()' => [static function (Metadata $metadata): void {
            self::assertThrowsInvalidArgument(static fn (): mixed => $metadata['expired']);
        }];
        yield 'count()' => [count(...)];
        yield 'iteration' => [iterator_to_array(...)];
        yield 'toArray()' => [static fn (Metadata $metadata): array => $metadata->toArray()];
    }

    /**
     * @param \Closure(): mixed $call
     */
    private static function assertThrowsInvalidArgument(\Closure $call): void
    {
        try {
            $call();
        } catch (\InvalidArgumentException) {
            return;
        }

        self::fail('Expected an InvalidArgumentException');
    }
}
