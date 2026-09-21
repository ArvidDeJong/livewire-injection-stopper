# darvis/livewire-injection-stopper

[![Latest version](https://img.shields.io/packagist/v/darvis/livewire-injection-stopper.svg)](https://packagist.org/packages/darvis/livewire-injection-stopper)
[![Tests](https://github.com/ArvidDeJong/livewire-injection-stopper/actions/workflows/tests.yml/badge.svg)](https://github.com/ArvidDeJong/livewire-injection-stopper/actions/workflows/tests.yml)
[![PHP version](https://img.shields.io/packagist/dependency-v/darvis/livewire-injection-stopper/php.svg)](https://packagist.org/packages/darvis/livewire-injection-stopper)
[![License](https://img.shields.io/packagist/l/darvis/livewire-injection-stopper.svg)](LICENSE)

A Laravel package for applications that use Livewire. Its middleware rejects requests from listed User-Agents and IP addresses, and Livewire update requests that send an array to a property that should hold a single value. It also keeps two bot-driven Livewire exceptions out of your error tracker, and its audit command lists public Livewire properties that probably need `#[Locked]`.

![The three parts: bot blocking, payload inspection and the locked-property audit](https://arviddejong.github.io/livewire-injection-stopper/assets/images/social-preview.png)

## Features

- Rejects a request whose User-Agent contains a listed pattern (`python`, `curl/`, `wget`, `go-http-client`, `axios`, named SEO and AI crawlers and more), with an allow list for uptime monitors. The default list holds no search engine.
- Rejects the exact IP addresses you list, and skips every check on the paths you whitelist, such as webhooks
- Inspects Livewire update requests and rejects an array sent to a property that looks scalar; by default that is every top-level property
- Answers `CannotUpdateLockedPropertyException` and the `TypeError` from a Livewire array assignment with the block response, and keeps them out of Laravel's exception reporting
- `php artisan livewire-injection-stopper:audit` lists public properties that probably need `#[Locked]`, with exit code 1 for CI
- A `RequestBlocked` event and a log line for every blocked request
- A Laravel Boost guideline and skill

It is not a firewall: a bot that sends a browser User-Agent passes, and scalar values are never inspected. See [what it does not stop](https://arviddejong.github.io/livewire-injection-stopper/how-it-works.html#what-it-does-not-stop).

## Requirements

PHP 8.2+, Laravel 11, 12 or 13, and Livewire 3 or 4.

## Installation

```bash
composer require darvis/livewire-injection-stopper
```

Nothing else is needed. The middleware joins the `web` group, the exception handling registers itself, and the audit command is available. To change a default, publish the config:

```bash
php artisan vendor:publish --tag=livewire-injection-stopper-config
```

One default to know before you install: every array sent to a **top-level** Livewire property is rejected. A multi-select bound with `wire:model="tags"` needs a nested key such as `form.tags`, or `block_all_array_injections` set to false. See [Payload injection](https://arviddejong.github.io/livewire-injection-stopper/payload-injection.html#components-with-array-properties).

## Quick start

Run the audit:

```bash
php artisan livewire-injection-stopper:audit
```

```
[CRITICAL]
  📍 app/Livewire/Checkout.php:10
     Property: $isAdmin (bool)
     💡 Add #[Locked] attribute above this property
```

Lock the property it names:

```php
<?php
// app/Livewire/Checkout.php

namespace App\Livewire;

use Livewire\Attributes\Locked;
use Livewire\Component;

class Checkout extends Component
{
    #[Locked]
    public bool $isAdmin = false;
}
```

A request that tries to change `isAdmin` now gets `403 Access Denied`, a log line that starts with `[LivewireInjectionStopper]` and a `RequestBlocked` event, and the exception Livewire throws is not reported. The [Quick start](https://arviddejong.github.io/livewire-injection-stopper/quick-start.html) page has the complete example, including a listener that counts blocked requests.

## Documentation

Full documentation at **[arviddejong.github.io/livewire-injection-stopper](https://arviddejong.github.io/livewire-injection-stopper/)**:

- [Installation](https://arviddejong.github.io/livewire-injection-stopper/installation.html): the steps, and how to check that it works
- [Quick start](https://arviddejong.github.io/livewire-injection-stopper/quick-start.html): audit, lock a property and count blocked requests
- [How it works](https://arviddejong.github.io/livewire-injection-stopper/how-it-works.html): the four checks in order, and what the package does not stop
- [Bot blocking](https://arviddejong.github.io/livewire-injection-stopper/bot-blocking.html): User-Agents, IP addresses, whitelisted paths, the response, the log line and the event
- [Payload injection](https://arviddejong.github.io/livewire-injection-stopper/payload-injection.html): the payload rules and the two silenced exceptions
- [Security audit](https://arviddejong.github.io/livewire-injection-stopper/security-audit.html): what the audit scans, its output and its limits
- [Configuration](https://arviddejong.github.io/livewire-injection-stopper/configuration.html): every key with its default
- [Testing](https://arviddejong.github.io/livewire-injection-stopper/testing.html): a complete feature test for your own app
- [Troubleshooting](https://arviddejong.github.io/livewire-injection-stopper/troubleshooting.html): a legitimate request is blocked, or nothing is
- [FAQ](https://arviddejong.github.io/livewire-injection-stopper/faq.html): short answers

## Laravel Boost

The package ships a guideline and a skill for [Laravel Boost](https://github.com/laravel/boost). Run `php artisan boost:install`, or `php artisan boost:update --discover` in a project that already uses Boost.

## Testing

```bash
composer test      # Pest
composer lint      # Pint, check only; composer format fixes
composer analyse   # Larastan, level 8
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md).

## Security

Found a way around the bot blocking or the payload inspection? Please report it privately; see [SECURITY.md](SECURITY.md).

## License

MIT, see [LICENSE](LICENSE). A package by [ARVID.NL](https://arvid.nl).
