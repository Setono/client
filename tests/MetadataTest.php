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
        $metadata = new Metadata();
        $metadata->set('foo', 'bar');
        $metadata['bar'] = 'baz';

        self::assertCount(2, $metadata);
        self::assertSame('bar', $metadata->get('foo'));
        self::assertSame('baz', $metadata['bar']);

        $metadata->remove('foo');
        unset($metadata['bar']);
        self::assertCount(0, $metadata);
        self::assertFalse($metadata->has('foo'));
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
}
