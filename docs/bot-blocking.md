---
title: "Bot blocking"
nav_order: 5
description: "Block requests by User-Agent pattern or exact IP address, let your uptime monitor through, whitelist webhook paths, and set the response, log line and event."
---

# Bot blocking

The middleware rejects requests by User-Agent and by IP address. Every setting on this page lives in `config/livewire-injection-stopper.php`. Publish that file first:

```bash
php artisan vendor:publish --tag=livewire-injection-stopper-config
```

## Which User-Agents are blocked

`blocked_user_agents` is a list of patterns. A request is blocked when its `User-Agent` header **contains** one of them. The comparison is case-insensitive and uses `str_contains()`. So `python` blocks `python-requests/2.31`, `Python-urllib/3.11` and every other client that names Python. `curl/` blocks `curl/8.4.0` but not a User-Agent that has the word "curl" without the slash.

The defaults, 27 patterns in two groups:

| Group | Patterns |
| --- | --- |
| HTTP clients and scripts | `python`, `aiohttp`, `httpx`, `curl/`, `wget`, `scrapy`, `postman`, `insomnia`, `httpie`, `go-http-client`, `java/`, `okhttp`, `axios`, `node-fetch`, `libwww-perl` |
| SEO and AI crawlers | `ahrefsbot`, `semrushbot`, `dotbot`, `mj12bot`, `blexbot`, `dataforseo`, `bytespider`, `petalbot`, `gptbot`, `claudebot`, `ccbot`, `anthropic` |

The default list holds no search engine and no generic word such as `bot`, `spider` or `crawler`. Those words are part of `Googlebot` and `bingbot`, so adding one blocks them too.

Two limits:

- A request without a `User-Agent` header, or with an empty one, is never blocked by this check.
- A client can send any User-Agent it likes. A bot that sends a browser string passes.

### Let a client through with the allow list

`allowed_user_agents` wins over `blocked_user_agents`. A User-Agent that contains an allowed pattern passes this check, whatever else it contains. The defaults are `sentryuptimebot`, `uptimerobot`, `pingdom` and `statuscake`.

If you add a generic word to the blocked list, add your uptime monitor and the search engines to the allowed list first:

```php
// config/livewire-injection-stopper.php

'allowed_user_agents' => [
    'uptimerobot',
    'googlebot',
    'bingbot',
],

'blocked_user_agents' => [
    // ... the defaults
    'bot',
],
```

Because a client chooses its own User-Agent, a script that sends an allowed pattern passes this check as well.

## Which IP addresses are blocked

`blocked_ips` holds exact addresses. The default list is empty. There is no CIDR or wildcard matching; for ranges, use your firewall or a proxy rule.

```php
// config/livewire-injection-stopper.php

'blocked_ips' => [
    '203.0.113.42',
],
```

The address comes from `$request->ip()`. When the application sits behind a load balancer or a proxy, configure Laravel's [trusted proxies](https://laravel.com/docs/requests#configuring-trusted-proxies). Without that, `$request->ip()` returns the address of the proxy for every request.

## Paths that skip every check

`whitelist_routes` lists paths that skip **every** check: the IP check, the User-Agent check and the payload inspection. Use it for webhooks (URLs that another service calls with a scripted client).

Patterns are matched with PHP's `fnmatch()` against the request path without a leading slash. `*` also matches slashes, so `api/webhooks/*` matches `api/webhooks/stripe` and `api/webhooks/stripe/events`, but not `api/webhooks` itself.

```php
// config/livewire-injection-stopper.php

'whitelist_routes' => [
    'api/mollie-webhook',
    'api/webhooks/*',
],
```

The two defaults are examples; replace them with your own paths. Keep the patterns narrow: a pattern such as `*` turns the filter off.

The whitelist only matters for routes the middleware runs on. Routes outside the `web` middleware group, such as everything in `routes/api.php`, are not filtered in the first place.

## The response a blocked request gets

```php
// config/livewire-injection-stopper.php

'response_message' => 'Access Denied',
'response_status' => 403,
```

`response_message` is the response body. It is sent with Laravel's default `Content-Type`, `text/html; charset=utf-8`, so keep it plain text. `response_status` can be any HTTP status; `404` makes a blocked request look like a missing page.

## The log line

With `log_blocked_requests` enabled (the default), every blocked request writes a warning to the default log channel. In a default Laravel application that is `storage/logs/laravel.log`:

```
[2026-09-21 10:45:11] production.WARNING: [LivewireInjectionStopper] Blocked User-Agent: python-requests/2.31.0 {"reason":"blocked_user_agent","ip":"203.0.113.42","user_agent":"python-requests/2.31.0","url":"https://example.com/contact","method":"POST"}
```

The four messages are:

- `[LivewireInjectionStopper] Blocked IP: 203.0.113.42`
- `[LivewireInjectionStopper] Blocked User-Agent: python-requests/2.31.0`
- `[LivewireInjectionStopper] Suspicious Livewire payload detected`
- `[LivewireInjectionStopper] Blocked Livewire property manipulation attempt`

The package treats these messages as public API and keeps them the same within 1.x, so a log alert keeps matching.

## The `RequestBlocked` event

`Darvis\LivewireInjectionStopper\Events\RequestBlocked` is dispatched for every blocked request and every silenced exception, whether logging is on or off. It carries:

| Property | Value |
| --- | --- |
| `reason` | `blocked_ip`, `blocked_user_agent`, `suspicious_payload` or `locked_property`. Also available as the constants `RequestBlocked::BLOCKED_IP`, `BLOCKED_USER_AGENT`, `SUSPICIOUS_PAYLOAD` and `LOCKED_PROPERTY` |
| `ip` | the client IP, or null |
| `userAgent` | the `User-Agent` header, or null |
| `url` | the full URL of the request |
| `exception` | the silenced exception, or null for a block by the request filter |

```php
<?php
// app/Providers/AppServiceProvider.php, inside boot()

use Darvis\LivewireInjectionStopper\Events\RequestBlocked;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

Event::listen(RequestBlocked::class, function (RequestBlocked $event): void {
    if ($event->reason === RequestBlocked::BLOCKED_IP) {
        return;
    }

    Cache::increment('blocked-requests:'.now()->format('Y-m-d'));
});
```

This listener counts every block per day, except the ones for a listed IP address.

## Applying the middleware elsewhere

The middleware is appended to the `web` group automatically. For another group or a single route, use the alias:

```php
<?php
// routes/api.php

use Illuminate\Support\Facades\Route;

Route::middleware('livewire-injection-stopper')->group(function () {
    // ...
});
```

Every route in this group now gets the same four checks.

There is no master switch. To turn the request filter off without removing the package, empty `blocked_user_agents` and `blocked_ips` and set `check_payload_injection` to false.

## Checking a request yourself

The `LivewireInjectionStopper` facade (a static shortcut to the manager class) exposes the checks. `check($request)` runs all four and returns the reason or null. The individual checks are there too:

```php
<?php

use Darvis\LivewireInjectionStopper\Facades\LivewireInjectionStopper;

LivewireInjectionStopper::check($request);                          // 'blocked_ip', ... or null
LivewireInjectionStopper::isBlockedUserAgent($request->userAgent()); // bool
LivewireInjectionStopper::isBlockedIp($request->ip());               // bool
LivewireInjectionStopper::isWhitelisted($request->path());           // bool
LivewireInjectionStopper::hasSuspiciousPayload($request);            // bool
```

`isBlockedUserAgent()`, `isBlockedIp()` and `hasSuspiciousPayload()` do not look at the whitelist; only `check()` does.
