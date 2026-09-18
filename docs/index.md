---
title: Home
nav_order: 1
description: Blocks spam bots and manipulated Livewire payloads in Laravel apps, keeps the resulting exceptions out of Sentry, and audits Livewire components for unlocked properties.
permalink: /
---

# Livewire Injection Stopper

Three layers of protection for a **Laravel** application that uses **Livewire**. A middleware that rejects scripted clients, listed IP addresses and manipulated Livewire payloads before they reach a component. An exception handler that answers bot-driven Livewire exceptions with a 403 instead of reporting them to Sentry. And an audit command that finds public properties an attacker could change from the browser.

![The three layers: bot blocking, payload inspection and the locked-property audit](assets/images/social-preview.png)

```bash
composer require darvis/livewire-injection-stopper
```

Requires PHP 8.2+, Laravel 11, 12 or 13, and Livewire 3 or 4. No setup is needed: the middleware joins the `web` group on install.

## Find properties that need `#[Locked]`

```bash
php artisan livewire-injection-stopper:audit
```

```
🔍 Scanning Livewire components for security issues...

⚠️  Potential vulnerabilities found:

[CRITICAL]
  📍 app/Livewire/Checkout.php:14
     Property: $isAdmin (bool)
     💡 Add #[Locked] attribute above this property
```

The command exits with code 1 when it finds something, so it fits in CI.

## Count what gets blocked

```php
use Darvis\LivewireInjectionStopper\Events\RequestBlocked;
use Illuminate\Support\Facades\Event;

Event::listen(RequestBlocked::class, function (RequestBlocked $event) {
    // $event->reason: blocked_ip, blocked_user_agent, suspicious_payload or locked_property
    // $event->ip, $event->userAgent, $event->url, $event->exception
});
```

## Change the defaults

```bash
php artisan vendor:publish --tag=livewire-injection-stopper-config
```

Blocked and allowed User-Agents, blocked IPs, whitelisted routes, the response, logging, the payload rules and the exception silencing all live in `config/livewire-injection-stopper.php`. See [Configuration](configuration.md).

## What you get

- Rejects scripted HTTP clients (python-requests, curl, wget, Go-http-client and more) and named SEO and AI crawlers, with a whitelist for uptime monitors. Search engines are not blocked.
- Rejects arrays sent to scalar Livewire properties, the usual first step of a type confusion attack
- Answers `CannotUpdateLockedPropertyException` and Livewire `TypeError`s from array assignment with the block response, and keeps them out of Sentry
- `RequestBlocked` event and a log line for every blocked request
- `livewire-injection-stopper:audit` for public properties that should be `#[Locked]`
- A Laravel Boost guideline and skill for AI assistants in your project

## Read next

- [How it works](how-it-works.md): the order of the checks, and what the package does not stop
- [Bot blocking](bot-blocking.md): User-Agents, IP addresses, whitelisted routes, the response and the event
- [Payload injection](payload-injection.md): the Livewire payload rules and the exception silencing
- [Security audit](security-audit.md): what the audit command flags and how to fix it
- [FAQ](faq.md)
