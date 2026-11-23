# PHP abstraction for identifying a browser client

[![Latest Version][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE)
[![Build Status][ico-github-actions]][link-github-actions]
[![Code Coverage][ico-code-coverage]][link-code-coverage]
[![Mutation testing][ico-infection]][link-infection]

This library provides a small, framework-agnostic value object for identifying a browser client: a stable id
together with arbitrary, optionally expiring metadata. A companion `Cookie` class serializes that id — along with a
format version and "first/last seen" timestamps — to and from a cookie value, so the same visitor can be recognized
across requests.

## Installation

```bash
composer require setono/client
```

When you don't supply an id yourself, the library generates a UUIDv7. To use that, install one of the supported UUID
libraries (if both are installed, `symfony/uid` is preferred):

```bash
# If you want to use symfony/uid
composer require symfony/uid

# If you want to use ramsey/uuid
composer require ramsey/uuid
```

## Usage

### The client

```php
use Setono\Client\Client;

// A new client with a generated UUIDv7 id and empty metadata
$client = new Client();

// ...or built from an existing id and metadata
$client = new Client('a3f1c0de-...', ['plan' => 'pro']);

$client->id;       // the identifier (string, readonly)
(string) $client;  // the same id — Client is Stringable
$client->metadata; // the Metadata object (readonly)
```

### Metadata

`Metadata` is a `string => mixed` bag. It implements `ArrayAccess`, `Countable` and `IteratorAggregate`, so you can
use it like an array:

```php
$metadata = $client->metadata;

$metadata->set('plan', 'pro');
$metadata->has('plan');     // true
$metadata->get('plan');     // 'pro' — throws InvalidArgumentException if the key is missing or expired
$metadata->remove('plan');

// array access, counting and iteration all work
$metadata['plan'] = 'pro';
isset($metadata['plan']);   // true
unset($metadata['plan']);
count($metadata);

foreach ($metadata as $key => $value) {
    // ...
}
```

#### Expiring entries

Pass a TTL (in seconds) as the third argument to `set()`. Expired entries are pruned lazily: they are no longer
returned by `get()`/`has()` and are excluded from iteration and `count()`.

```php
// "promo" expires one hour from now
$metadata->set('promo', 'BLACKFRIDAY', ttl: 3600);
```

`set()` without a TTL stores the key with no expiry, and removes any expiry the key had. To change a value and keep it
expiring, pass the TTL again:

```php
$metadata->set('promo', 'CYBERMONDAY');            // "promo" no longer expires
$metadata->set('promo', 'CYBERMONDAY', ttl: 3600); // expires one hour from now again
```

#### Tracking changes

`hasChanged()` tells you whether anything has altered the values or expiry times since the object was created, so you
can skip storing metadata that wasn't changed:

```php
$metadata = new Metadata($storedData);
$metadata->hasChanged(); // false, whatever data it was given

$metadata->set('plan', 'pro');
$metadata->hasChanged(); // true, unless 'plan' already held 'pro' with no expiry
```

A call counts as a change when the values or expiry times after it differ from those before it:

- `set()` of a new key, of a different value (compared with `!==`), with a TTL that moves the key's expiry, or without a
  TTL on a key that has an expiry. Setting a key to the value and expiry it already has is not a change.
- `remove()` or `unset()` of a key that exists.
- Dropping an expired key, whichever call drops it: `has()`, `get()`, array access, `count()`, iteration or `toArray()`.

Reading live keys and `jsonSerialize()` are not changes, and neither is a call that throws. Once `hasChanged()` returns
`true` it stays `true`: setting a value back doesn't undo it, and nothing resets it.

### Persisting and restoring

`Metadata::toArray()` returns everything needed to rebuild the object — including the expiry bookkeeping — so you can
store it (in a database, cache, session, ...) and reconstruct the client later with the timestamps intact:

```php
$id   = $client->id;
$data = $client->metadata->toArray();

// ...later
$client = new Client($id, $data);
```

`Client` and `Metadata` are also `JsonSerializable`:

```php
json_encode($client); // {"id":"a3f1c0de-...","metadata":{"plan":"pro"}}
```

### Storing the id in a cookie

The `Cookie` class converts a client id to and from a cookie value. The value also carries a format version and the
timestamps for when the client was first and last seen. The library only produces and parses the string — reading the
request cookie and writing it to the response is left to your HTTP layer.

```php
use Setono\Client\Cookie;

// firstSeenAt and lastSeenAt default to the current time
$cookie = new Cookie($client->id);

(string) $cookie; // "2.1700000000.1700000000.a3f1c0de-..." (version.firstSeenAt.lastSeenAt.clientId)

// Parse an incoming value. A bare id (no dots) is treated as a legacy v1 cookie and upgraded.
$cookie = Cookie::fromString($_COOKIE['_client'] ?? '');

$cookie->clientId;
$cookie->version;
$cookie->firstSeenAt;
$cookie->lastSeenAt;

// Cookie is immutable; "touch" the last-seen timestamp by deriving a new instance
$cookie = $cookie->withLastSeenAt(time());
```

[ico-version]: https://poser.pugx.org/setono/client/v/stable
[ico-license]: https://poser.pugx.org/setono/client/license
[ico-github-actions]: https://github.com/setono/client/workflows/build/badge.svg
[ico-code-coverage]: https://codecov.io/gh/setono/client/branch/master/graph/badge.svg
[ico-infection]: https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2FSetono%2Fclient%2Fmaster

[link-packagist]: https://packagist.org/packages/setono/client
[link-github-actions]: https://github.com/setono/client/actions
[link-code-coverage]: https://codecov.io/gh/setono/client
[link-infection]: https://dashboard.stryker-mutator.io/reports/github.com/Setono/client/master
