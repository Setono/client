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

    /**
     * @param array<string, mixed> $metadata The metadata. It may include the reserved "__expires" key, as produced
     *                                        by self::toArray(), to restore the expiry timestamps of the other keys
     */
    public function __construct(array $metadata = [])
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

    public function has(string $key): bool
    {
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
     * @param int|null $ttl the time to live for the key (in seconds)
     *
     * @throws \InvalidArgumentException if the key is reserved
     */
    public function set(string $key, mixed $value, ?int $ttl = null): void
    {
        if (self::EXPIRES_KEY === $key) {
            throw new \InvalidArgumentException(sprintf('The key "%s" is reserved', self::EXPIRES_KEY));
        }

        $this->data[$key] = $value;

        if (null !== $ttl) {
            if (0 >= $ttl) {
                throw new \InvalidArgumentException('The ttl must be greater than 0');
            }

            $this->expires[$key] = time() + $ttl;
        }
    }

    public function remove(string $key): void
    {
        unset($this->data[$key], $this->expires[$key]);
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
        $now = time();

        foreach ($this->expires as $key => $expiresAt) {
            if ($expiresAt < $now) {
                $this->remove($key);
            }
        }
    }
}
