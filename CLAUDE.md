# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

`setono/client` is a small, dependency-free PHP library (PHP >= 8.1) that provides a value-object abstraction for identifying a browser client: a `Client` (id + metadata), a `Metadata` bag with per-key TTL, and a `Cookie` serializer. It is a library, not an application — there is nothing to "run". The whole public surface is three classes in `src/`.

## Commands

The dev tooling is the inlined `setono/code-quality-pack` (the individual packages live directly in `require-dev`, not behind the meta-package). Composer scripts (also exposed as the user's zsh aliases noted in parentheses):

- `composer analyse` (`ca`) — PHPStan at `level: max` (config in `phpstan.dist.neon`)
- `composer check-style` — ECS dry run; `composer fix-style` (`cf`) auto-fixes
- `composer phpunit` (`phpunit`) — run the test suite
- `vendor/bin/composer-dependency-analyser` — verify declared vs. used dependencies (config in `composer-dependency-analyser.php`)
- `vendor/bin/infection` (`infection`) — mutation testing (thresholds: minMsi 90.48, minCoveredMsi 93.83)

Run a single test:

```bash
vendor/bin/phpunit --filter it_sets_and_gets_and_removes
vendor/bin/phpunit tests/MetadataTest.php
```

Coverage requires the `pcov` (or xdebug) extension; CI runs `phpunit --coverage-clover`.

## Architecture

- **`Client`** — immutable value object: `readonly string $id` + `readonly Metadata $metadata`. If no id is passed, it generates a UUIDv7, preferring `symfony/uid` and falling back to `ramsey/uuid` (one of them must be installed, or the constructor throws). Both are dev/optional dependencies — `composer-dependency-analyser.php` ignores `DEV_DEPENDENCY_IN_PROD` for them precisely because the library references them in `src/` without requiring them. `Stringable` (returns the id) and `JsonSerializable`. The ramsey/`default`-throw match arms are unreachable in CI (symfony/uid is always installed in dev), so Infection reports them as escaped — that is expected, not a coverage gap.

- **`Metadata`** — a `string => mixed` bag implementing `ArrayAccess`, `Countable`, `IteratorAggregate`, `JsonSerializable`. Internally it keeps **two** properties: `$data` (`array<string, mixed>`, the user entries) and `$expires` (`array<string, int>`, key → unix-expiry-timestamp). They are merged into a single array only for serialization, where the expiry map surfaces under the reserved key `Metadata::EXPIRES_KEY` (`__expires`) appended last; writing `__expires` via `set()` is forbidden. Expiry is lazy: `has()`/`count()`/`toArray()`/`getIterator()` prune on access (`jsonSerialize()` deliberately does **not** prune). Two serialization paths differ intentionally — `toArray()` and `jsonSerialize()` include `__expires` so the object can be reconstructed, while iteration (`getIterator`) yields only `$data`. The reconstruction round-trip (`new Metadata($existing->toArray())`) depends on the constructor splitting `__expires` back out — it validates each entry (`is_string` key, `is_int` value) and silently drops malformed ones. Note this split is why PHPStan stays clean at `level: max`: cramming the typed expiry map into the same `mixed` bag produced contradictory array-shape types.

- **`Cookie`** — serializes a client id to/from a dotted string `version.firstSeenAt.lastSeenAt.clientId` (current `version = 2`). `fromString()` is backward-compatible: a string with no dots is treated as a legacy v1 bare client id and upgraded to v2. Immutable; `withLastSeenAt()` returns a new instance.

## Testing time-based expiry

The library calls the **unqualified** `time()`, which in namespace `Setono\Client` resolves to a namespaced `time()` if one exists. `tests/StaticClock.php` defines exactly that — a `Setono\Client\time()` shadow backed by `StaticClock`; tests call `StaticClock::setTime()` to freeze/advance the clock deterministically. That file is loaded eagerly via the `files` autoloader (`autoload-dev` in `composer.json`), **not** lazily in a `setUp()`, because PHP binds each unqualified-function call site to the global or namespaced function on first execution and caches it forever — if any `src/` `time()` call runs (e.g. via a `Client`/`Metadata` test in another file) before the shadow is defined, that call site permanently binds to global `time()` and the clock can no longer be controlled. When testing TTL/expiry, follow this pattern rather than sleeping; do not fully-qualify `\time()` in `src/` or you will break the override.

## Conventions

- `declare(strict_types=1)` in every file; PSR-4 maps `Setono\Client\` to both `src/` (autoload) and `tests/` (autoload-dev).
- Code style is Sylius Labs' standard via ECS.
- CI (`.github/workflows/build.yaml`) also runs `composer validate --strict` and `composer normalize --dry-run`; PRs additionally run a Roave backward-compatibility check, so avoid BC breaks to the public API. Note the `Metadata` serialization order is **not** part of that contract (the reconstruction round-trip is key-based, not order-based).
