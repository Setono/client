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

    public function __construct(
        /**
         * @var array{__expires?: array<string, int>, ...<string, mixed>} $metadata
         */
        private array $metadata = [],
    ) {
    }

    /**
     * @psalm-assert-if-true mixed $this->metadata[$key]
     */
    public function has(string $key): bool
    {
        if (!array_key_exists($key, $this->metadata)) {
            return false;
        }

        if (isset($this->metadata[self::EXPIRES_KEY][$key]) && $this->metadata[self::EXPIRES_KEY][$key] < time()) {
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

        return $this->metadata[$key];
    }

    /**
     * @param int|null $ttl the time to live for the key (in seconds)
     *
     * @throws \InvalidArgumentException if the key is reserved
     */
    public function set(string $key, mixed $value, int $ttl = null): void
    {
        if (self::EXPIRES_KEY === $key) {
            throw new \InvalidArgumentException(sprintf('The key "%s" is reserved', self::EXPIRES_KEY));
        }

        $this->metadata[$key] = $value;

        if (null !== $ttl) {
            if (0 >= $ttl) {
                throw new \InvalidArgumentException('The ttl must be greater than 0');
            }

            $this->metadata[self::EXPIRES_KEY][$key] = time() + $ttl;
        }
    }

    public function remove(string $key): void
    {
        unset($this->metadata[$key]);

        if (isset($this->metadata[self::EXPIRES_KEY][$key])) {
            unset($this->metadata[self::EXPIRES_KEY][$key]);
        }
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

        $c = count($this->metadata);

        return isset($this->metadata[self::EXPIRES_KEY]) ? $c - 1 : $c;
    }

    public function getIterator(): \ArrayIterator
    {
        $metadata = $this->toArray();
        unset($metadata[self::EXPIRES_KEY]);

        return new \ArrayIterator($metadata);
    }

    public function jsonSerialize(): array
    {
        return $this->metadata;
    }

    /**
     * Please notice that this method returns the data to be able to reconstruct the object.
     * This means it includes the expired keys.
     *
     * When you iterate the Metadata object, the expired keys are not included.
     *
     * @return array{__expires?: array<string, int>, ...<string, mixed>}
     */
    public function toArray(): array
    {
        $this->pruneExpired();

        return $this->metadata;
    }

    private function pruneExpired(): void
    {
        $now = time();

        foreach ($this->metadata[self::EXPIRES_KEY] ?? [] as $key => $expiresAt) {
            if ($expiresAt < $now) {
                $this->remove($key);
            }
        }

        if (isset($this->metadata[self::EXPIRES_KEY]) && [] === $this->metadata[self::EXPIRES_KEY]) {
            unset($this->metadata[self::EXPIRES_KEY]);
        }
    }
}
