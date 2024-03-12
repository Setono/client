<?php

declare(strict_types=1);

namespace Setono\Client;

final class Client implements \Stringable
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
     * @param array<string, mixed>|Metadata $metadata
     */
    public function __construct(string $id = null, array|Metadata $metadata = [])
    {
        if (null === $id) {
            $id = match (true) {
                class_exists('Symfony\Component\Uid\Uuid') => (string) call_user_func(['Symfony\Component\Uid\Uuid', 'v7']),
                class_exists('Ramsey\Uuid\Uuid') => (string) call_user_func(['Ramsey\Uuid\Uuid', 'uuid7']),
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
}
