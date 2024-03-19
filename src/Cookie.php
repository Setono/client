<?php

declare(strict_types=1);

namespace Setono\Client;

class Cookie implements \Stringable
{
    /**
     * The timestamp where the user was first seen
     */
    public readonly int $firstSeenAt;

    /**
     * The timestamp where the user was last seen
     */
    public readonly int $lastSeenAt;

    final public function __construct(
        /**
         * A unique identifier for the client
         */
        public readonly string $clientId,
        /**
         * The version of the cookie.
         * We consider the first versions of https://github.com/Setono/client-id (and its related packages) to be version 1
         */
        public readonly int $version = 2,
        int $firstSeenAt = null,
        int $lastSeenAt = null,
    ) {
        $this->firstSeenAt = $firstSeenAt ?? time();
        $this->lastSeenAt = $lastSeenAt ?? time();
    }

    /**
     * @throws \InvalidArgumentException if the cookie is not valid
     */
    public static function fromString(string $cookie): static
    {
        if ('' === $cookie) {
            throw new \InvalidArgumentException('The cookie is not valid');
        }

        $parts = explode('.', $cookie, 4);
        if (count($parts) === 1) {
            return new static($parts[0]); // this effectively converts the v1 cookie to a v2 cookie
        }

        if (count($parts) !== 4) {
            throw new \InvalidArgumentException('The cookie is not valid');
        }

        [$version, $firstSeenAt, $lastSeenAt, $clientId] = $parts;

        if (!is_numeric($version)) {
            throw new \InvalidArgumentException('The version part of the cookie is not valid');
        }
        $version = (int) $version;

        if (!is_numeric($firstSeenAt)) {
            throw new \InvalidArgumentException('The first seen at part of the cookie is not valid');
        }
        $firstSeenAt = (int) $firstSeenAt;

        if (!is_numeric($lastSeenAt)) {
            throw new \InvalidArgumentException('The last seen at part of the cookie is not valid');
        }
        $lastSeenAt = (int) $lastSeenAt;

        return new static($clientId, $version, $firstSeenAt, $lastSeenAt);
    }

    public function withLastSeenAt(int $lastSeenAt): static
    {
        return new static($this->clientId, $this->version, $this->firstSeenAt, $lastSeenAt);
    }

    public function toString(): string
    {
        return sprintf('%d.%d.%d.%s', $this->version, $this->firstSeenAt, $this->lastSeenAt, $this->clientId);
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
