<?php

declare(strict_types=1);

namespace Setono\Client;

class Client implements \Stringable, \JsonSerializable
{
    /**
     * The unique identifier of the client. This could be a UUID for example
     */
    public readonly string $id;

    /**
     * Metadata for this client. This could be anything you want to save on the client
     */
    public readonly Metadata $metadata;

    /**
     * @param string|null $id if null a new id will be generated
     * @param Metadata|array{__expires?: array<string, int>, ...<string, mixed>} $metadata
     */
    public function __construct(string $id = null, array|Metadata $metadata = [])
    {
        if (null === $id) {
            $id = (string) match (true) {
                class_exists(\Symfony\Component\Uid\Uuid::class) => \Symfony\Component\Uid\Uuid::v7(),
                class_exists(\Ramsey\Uuid\Uuid::class) => \Ramsey\Uuid\Uuid::uuid7(),
                default => throw new \RuntimeException('You need to install symfony/uid or ramsey/uuid to generate a UUID'),
            };
        }

        $this->id = $id;
        $this->metadata = $metadata instanceof Metadata ? $metadata : new Metadata($metadata);
    }

    public function toString(): string
    {
        return $this->id;
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'metadata' => $this->metadata,
        ];
    }
}
