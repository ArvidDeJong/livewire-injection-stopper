# darvis/livewire-injection-stopper

[![Latest version](https://img.shields.io/packagist/v/darvis/livewire-injection-stopper.svg)](https://packagist.org/packages/darvis/livewire-injection-stopper)
[![Tests](https://github.com/ArvidDeJong/livewire-injection-stopper/actions/workflows/tests.yml/badge.svg)](https://github.com/ArvidDeJong/livewire-injection-stopper/actions/workflows/tests.yml)
[![Total downloads](https://img.shields.io/packagist/dt/darvis/livewire-injection-stopper.svg)](https://packagist.org/packages/darvis/livewire-injection-stopper)
[![PHP version](https://img.shields.io/packagist/dependency-v/darvis/livewire-injection-stopper/php.svg)](https://packagist.org/packages/darvis/livewire-injection-stopper)
[![License](https://img.shields.io/packagist/l/darvis/livewire-injection-stopper.svg)](LICENSE)

Blocks **spam bots** and **manipulated Livewire payloads** in a Laravel app, keeps the resulting Livewire exceptions out of Sentry, and audits Livewire components for public properties that need `#[Locked]`.

![The three layers: bot blocking, payload inspection and the locked-property audit](https://arviddejong.github.io/livewire-injection-stopper/assets/images/social-preview.png)

## Features

- 🤖 Rejects scripted HTTP clients (python-requests, curl, wget, Go-http-client, axios and more) and named SEO and AI crawlers by User-Agent, with a whitelist for uptime monitors. Search engines are never blocked.
- 🚫 Blocks the IP addresses you list, and skips webhooks and other whitelisted paths
- 🧪 Inspects Livewire update requests and rejects arrays sent to scalar properties before Livewire hydrates them
- 🔇 Answers `CannotUpdateLockedPropertyException` and Livewire `TypeError`s from array assignment with a 403 and keeps them out of Sentry, Flare and friends
- 🔍 `php artisan livewire-injection-stopper:audit` finds public properties an attacker could change from the browser
- 📣 `RequestBlocked` event and a log line for every blocked request
- 🤖 Laravel Boost guideline and skill included

## Requirements

PHP 8.2+, Laravel 11, 12 or 13, and Livewire 3 or 4.

## Installation

```bash
composer require darvis/livewire-injection-stopper
```

No setup is needed. The middleware joins the `web` group, the exception handling registers itself, and the audit command is available right away.

## Quick start: audit your components

```bash
php artisan livewire-injection-stopper:audit
```

```
[CRITICAL]
  📍 app/Livewire/Checkout.php:14
     Property: $isAdmin (bool)
     💡 Add #[Locked] attribute above this property
```

```php
use Livewire\Attributes\Locked;

class Checkout extends Component
{
    #[Locked]
    public bool $isAdmin = false;
}
```

The command exits with code 1 when it finds something, so it fits in CI.

## Quick start: count blocked requests

```php
use Darvis\LivewireInjectionStopper\Events\RequestBlocked;
use Illuminate\Support\Facades\Event;

Event::listen(RequestBlocked::class, function (RequestBlocked $event) {
    // $event->reason: blocked_ip, blocked_user_agent, suspicious_payload or locked_property
    // $event->ip, $event->userAgent, $event->url, $event->exception
});
```

## Quick start: change the defaults

```bash
php artisan vendor:publish --tag=livewire-injection-stopper-config
```

Blocked and allowed User-Agents, blocked IPs, whitelisted routes, the response, logging, the payload rules and the exception silencing all live in `config/livewire-injection-stopper.php`.

One default to know about: every array sent to a **top-level** Livewire property is rejected. A multi-select bound with `wire:model="tags"` needs a nested key such as `form.tags`, or `block_all_array_injections` set to false. See [Payload injection](https://arviddejong.github.io/livewire-injection-stopper/payload-injection.html).

## Documentation

Full documentation at **[arviddejong.github.io/livewire-injection-stopper](https://arviddejong.github.io/livewire-injection-stopper/)**:

- [How it works](https://arviddejong.github.io/livewire-injection-stopper/how-it-works.html): the order of the checks, and what the package does not stop
- [Bot blocking](https://arviddejong.github.io/livewire-injection-stopper/bot-blocking.html): User-Agents, IP addresses, whitelisted routes, the response and the event
- [Payload injection](https://arviddejong.github.io/livewire-injection-stopper/payload-injection.html): the payload rules and the exception silencing
- [Security audit](https://arviddejong.github.io/livewire-injection-stopper/security-audit.html): what the audit flags and its limits
- [Configuration](https://arviddejong.github.io/livewire-injection-stopper/configuration.html)
- [Testing](https://arviddejong.github.io/livewire-injection-stopper/testing.html)
- [FAQ](https://arviddejong.github.io/livewire-injection-stopper/faq.html)

## Security

Found a way around the bot blocking or the payload inspection? Please report it privately; see [SECURITY.md](SECURITY.md).

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md). Changes are listed in the [CHANGELOG](CHANGELOG.md).

## License

MIT, see [LICENSE](LICENSE). A package by [ARVID.NL](https://arvid.nl).
