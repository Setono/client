<?php

declare(strict_types=1);

namespace Setono\Client;

/**
 * A loader for Metadata::lazy() that counts how many times it is called
 */
final class CountingLoader
{
    public int $calls = 0;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(private readonly array $data = [])
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(): array
    {
        ++$this->calls;

        return $this->data;
    }
}
