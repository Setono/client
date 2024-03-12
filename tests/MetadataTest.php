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
    public function it_sets_and_gets(): void
    {
        $metadata = new Metadata();
        $metadata->set('foo', 'bar');
        $metadata['bar'] = 'baz';

        self::assertCount(2, $metadata);
        self::assertSame('bar', $metadata->get('foo'));
        self::assertSame('baz', $metadata['bar']);
    }
}
