<?php

declare(strict_types=1);

namespace Setono\Client;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;

final class ClientTest extends TestCase
{
    /**
     * @test
     */
    public function it_instantiates_with_default_values(): void
    {
        $client = new Client();
        $uuid = Uuid::fromString($client->id);

        self::assertInstanceOf(UuidV7::class, $uuid);
        self::assertInstanceOf(Metadata::class, $client->metadata);
    }

    /**
     * @test
     */
    public function it_instantiates_with_metadata_array(): void
    {
        $client = new Client(metadata: ['foo' => 'bar']);

        self::assertInstanceOf(Metadata::class, $client->metadata);
    }

    /**
     * @test
     */
    public function it_instantiates_with_metadata_object(): void
    {
        $metadata = new Metadata(['foo' => 'bar']);
        $client = new Client(metadata: $metadata);

        self::assertSame($metadata, $client->metadata);
    }

    /**
     * @test
     */
    public function it_json_serializes(): void
    {
        $client = new Client('id', ['foo' => 'bar', 'bar' => 'baz']);
        $json = json_encode($client);

        self::assertSame('{"id":"id","metadata":{"foo":"bar","bar":"baz"}}', $json);
    }
}
