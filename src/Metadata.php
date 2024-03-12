<?php

declare(strict_types=1);

namespace Setono\Client;

/**
 * @implements \ArrayAccess<string, mixed>
 * @implements \IteratorAggregate<string, mixed>
 */
final class Metadata implements \ArrayAccess, \Countable, \IteratorAggregate, \JsonSerializable
{
    public function __construct(
        /**
         * @var array<string, mixed> $metadata
         */
        private array $metadata = [],
    ) {
    }

    /**
     * @psalm-assert-if-true mixed $this->metadata[$key]
     */
    public function has(string $key): bool
    {
        return isset($this->metadata[$key]);
    }

    public function get(string $key): mixed
    {
        if (!$this->has($key)) {
            throw new \InvalidArgumentException(sprintf('The key %s does not exist', $key));
        }

        return $this->metadata[$key];
    }

    public function set(string $key, mixed $value): void
    {
        $this->metadata[$key] = $value;
    }

    public function remove(string $key): void
    {
        unset($this->metadata[$key]);
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
        return count($this->metadata);
    }

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->metadata);
    }

    public function jsonSerialize(): array
    {
        return $this->metadata;
    }
}
