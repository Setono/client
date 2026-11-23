<?php

declare(strict_types=1);

namespace Setono\Client;

/**
 * @implements \ArrayAccess<string, mixed>
 * @implements \IteratorAggregate<string, mixed>
 */
class Metadata implements \ArrayAccess, \Countable, \IteratorAggregate, \JsonSerializable
{
    final public const EXPIRES_KEY = '__expires';

    /** @var array<string, mixed> */
    private array $data = [];

    /**
     * Maps a metadata key to the unix timestamp when it expires
     *
     * @var array<string, int>
     */
    private array $expires = [];

    private bool $changed = false;

    /**
     * Returns the entries of lazy metadata on first use. Null once loaded, and for metadata that isn't lazy
     *
     * @var (\Closure(): array<string, mixed>)|null
     */
    private ?\Closure $loader = null;

    /**
     * @param array<string, mixed> $metadata The metadata. It may include the reserved "__expires" key, as produced
     *                                        by self::toArray(), to restore the expiry timestamps of the other keys
     */
    public function __construct(array $metadata = [])
    {
        $this->populate($metadata);
    }

    /**
     * Creates metadata that calls the loader the first time a call reads or alters it
     *
     * @param callable(): array<string, mixed> $loader Returns what the constructor takes, including the reserved
     *                                                 "__expires" key
     */
    public static function lazy(callable $loader): self
    {
        $metadata = new self();
        $metadata->loader = $loader(...);

        return $metadata;
    }

    public function has(string $key): bool
    {
        $this->load();

        if (!array_key_exists($key, $this->data)) {
            return false;
        }

        if (isset($this->expires[$key]) && $this->expires[$key] < time()) {
            $this->remove($key);

            return false;
        }

        return true;
    }

    public function get(string $key): mixed
    {
        if (!$this->has($key)) {
            throw new \InvalidArgumentException(sprintf('The key %s does not exist', $key));
        }

        return $this->data[$key];
    }

    /**
     * @param int|null $ttl the time to live for the key (in seconds). Null means the key never expires: any expiry
     *                      it had is removed
     *
     * @throws \InvalidArgumentException if the key is reserved or the ttl is 0 or less
     */
    public function set(string $key, mixed $value, ?int $ttl = null): void
    {
        if (self::EXPIRES_KEY === $key) {
            throw new \InvalidArgumentException(sprintf('The key "%s" is reserved', self::EXPIRES_KEY));
        }

        if (null !== $ttl && 0 >= $ttl) {
            throw new \InvalidArgumentException('The ttl must be greater than 0');
        }

        $this->load();

        if (!array_key_exists($key, $this->data) || $this->data[$key] !== $value) {
            $this->changed = true;
        }

        $this->data[$key] = $value;

        if (null !== $ttl) {
            $expiresAt = time() + $ttl;

            if (($this->expires[$key] ?? null) !== $expiresAt) {
                $this->changed = true;
            }

            $this->expires[$key] = $expiresAt;
        } elseif (array_key_exists($key, $this->expires)) {
            $this->changed = true;

            unset($this->expires[$key]);
        }
    }

    /**
     * Expired keys are dropped through this method too. Keep it that way: released versions of setono/client-bundle
     * override it to notice dropped keys
     */
    public function remove(string $key): void
    {
        $this->load();

        if (array_key_exists($key, $this->data) || array_key_exists($key, $this->expires)) {
            $this->changed = true;
        }

        unset($this->data[$key], $this->expires[$key]);
    }

    /**
     * Returns true once a call has altered the values or expiry times. Constructing the object or loading lazy metadata
     * is not a change, setting a value back does not undo one, and nothing resets it. It never calls the loader of lazy
     * metadata: lazy metadata that was never loaded hasn't changed
     */
    public function hasChanged(): bool
    {
        return $this->changed;
    }

    public function offsetExists($offset): bool
    {
        return $this->has($offset);
    }

    public function offsetGet($offset): mixed
    {
        return $this->get($offset);
    }

    public function offsetSet($offset, mixed $value): void
    {
        if (null === $offset) {
            throw new \InvalidArgumentException('The offset cannot be null');
        }

        $this->set($offset, $value);
    }

    public function offsetUnset($offset): void
    {
        $this->remove($offset);
    }

    public function count(): int
    {
        $this->pruneExpired();

        return count($this->data);
    }

    /**
     * @return \ArrayIterator<string, mixed>
     */
    public function getIterator(): \ArrayIterator
    {
        $this->pruneExpired();

        return new \ArrayIterator($this->data);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $this->load();

        return $this->merged();
    }

    /**
     * Please notice that this method returns the data to be able to reconstruct the object.
     * This means it includes the expired keys.
     *
     * When you iterate the Metadata object, the expired keys are not included.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $this->pruneExpired();

        return $this->merged();
    }

    /**
     * Loads lazy metadata, then returns its properties without the loader, because closures can't be serialized.
     * The array has the property names PHP serializes by default, so the format stays the one setono/client 1.2
     * wrote, and unserializing gives metadata that isn't lazy. It's __serialize() rather than __sleep(), because
     * Symfony's LazyGhostTrait, which released versions of setono/client-bundle use, raises a notice for
     * names from __sleep() that it can't find
     *
     * @return array<mixed>
     */
    public function __serialize(): array
    {
        $this->load();

        $properties = (array) $this;
        unset($properties["\0" . self::class . "\0loader"]);

        return $properties;
    }

    /**
     * Returns the data with the reserved "__expires" key appended, i.e. the representation
     * that can be passed back to the constructor to reconstruct the object
     *
     * @return array<string, mixed>
     */
    private function merged(): array
    {
        $data = $this->data;

        if ([] !== $this->expires) {
            $data[self::EXPIRES_KEY] = $this->expires;
        }

        return $data;
    }

    private function pruneExpired(): void
    {
        $this->load();

        $now = time();

        foreach ($this->expires as $key => $expiresAt) {
            if ($expiresAt < $now) {
                $this->remove($key);
            }
        }
    }

    /**
     * Calls the loader of lazy metadata that hasn't loaded yet. If the loader throws, nothing changes and the next call
     * calls it again
     */
    private function load(): void
    {
        if (null === $this->loader) {
            return;
        }

        $this->populate(($this->loader)());
        $this->loader = null;
    }

    /**
     * Reads constructor input into metadata that holds no entries yet: a new object, or lazy metadata that is loading
     *
     * @param array<string, mixed> $metadata
     */
    private function populate(array $metadata): void
    {
        $expires = $metadata[self::EXPIRES_KEY] ?? [];
        unset($metadata[self::EXPIRES_KEY]);

        $this->data = $metadata;

        if (is_array($expires)) {
            foreach ($expires as $key => $expiresAt) {
                if (is_string($key) && is_int($expiresAt)) {
                    $this->expires[$key] = $expiresAt;
                }
            }
        }
    }
}
