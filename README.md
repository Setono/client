# PHP abstraction for identifying a browser client

[![Latest Version][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE)
[![Build Status][ico-github-actions]][link-github-actions]
[![Code Coverage][ico-code-coverage]][link-code-coverage]
[![Mutation testing][ico-infection]][link-infection]

## Installation

```bash
composer require setono/client
```

If you don't use your own client id generation strategy, you should also install either
the [symfony/uid](https://packagist.org/packages/symfony/uid) or the [ramsey/uuid](https://packagist.org/packages/ramsey/uuid) package:

```bash
# If you want to use symfony/uid
composer require symfony/uid

# If you want to use ramsey/uuid
composer require ramsey/uuid
```

## Usage

```php
use Setono\Client\Client;

// initialization with a generated id and an empty metadata object
$client = new Client();

// initialization with your own id and existing metadata
$client = new Client('my-client-id', ['foo' => 'bar']);

// get the client id
$id = $client->id;

// set metadata
$client->metadata->set('foo', 'bar');

// get metadata
$client->metadata->get('foo');

// remove metadata
$client->metadata->remove('foo');
```

There's also a `Cookie` class which can be used to store the client id in a cookie.


[ico-version]: https://poser.pugx.org/setono/client/v/stable
[ico-license]: https://poser.pugx.org/setono/client/license
[ico-github-actions]: https://github.com/setono/client/workflows/build/badge.svg
[ico-code-coverage]: https://codecov.io/gh/setono/client/branch/master/graph/badge.svg
[ico-infection]: https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2FSetono%2Fclient%2Fmaster

[link-packagist]: https://packagist.org/packages/setono/client
[link-github-actions]: https://github.com/setono/client/actions
[link-code-coverage]: https://codecov.io/gh/setono/client
[link-infection]: https://dashboard.stryker-mutator.io/reports/github.com/Setono/client/master
