---
title: Bot blocking
nav_order: 3
description: Block scripted clients and crawlers by User-Agent, allow your uptime monitor, block IP addresses, whitelist webhooks, and choose the response for blocked requests.
---

# Bot blocking

The middleware rejects requests by User-Agent and by IP address. Everything on this page lives in `config/livewire-injection-stopper.php`; publish it with `php artisan vendor:publish --tag=livewire-injection-stopper-config`.

## User-Agents

`blocked_user_agents` is a list of patterns. A request is blocked when its User-Agent **contains** one of them, compared case-insensitively with `str_contains()`. So `python` blocks `python-requests/2.31`, `Python-urllib/3.11` and every other client that names Python, and `curl/` blocks `curl/8.4.0` without touching a browser that mentions "curl" somewhere in a product name.

The defaults, grouped:

| Group | Patterns |
| --- | --- |
| HTTP clients and scripts | `python`, `aiohttp`, `httpx`, `curl/`, `wget`, `scrapy`, `postman`, `insomnia`, `httpie`, `go-http-client`, `java/`, `okhttp`, `axios`, `node-fetch`, `libwww-perl` |
| SEO and AI crawlers | `ahrefsbot`, `semrushbot`, `dotbot`, `mj12bot`, `blexbot`, `dataforseo`, `bytespider`, `petalbot`, `gptbot`, `claudebot`, `ccbot`, `anthropic` |

Search engines are not on the list, and the list deliberately has no generic words such as `bot`, `spider` or `crawler`: Googlebot, bingbot and most uptime monitors contain them.

### Allowed User-Agents

`allowed_user_agents` wins over `blocked_user_agents`. A User-Agent that contains an allowed pattern always passes, whatever else it contains. The defaults cover `sentryuptimebot`, `uptimerobot`, `pingdom` and `statuscake`.

If you do add a generic word to the blocked list, add your monitor here first:

```php
'blocked_user_agents' => [
    // ...
    'bot',
],

'allowed_user_agents' => [
    'uptimerobot',
    'googlebot',
    'bingbot',
],
```

A request without a User-Agent is never blocked by this check.

## IP addresses

`blocked_ips` holds exact addresses. There is no CIDR matching; for ranges, use your firewall or a proxy rule.

```php
'blocked_ips' => [
    '203.0.113.42',
],
```

The address comes from `$request->ip()`, so configure Laravel's trusted proxies when the app sits behind a load balancer. Otherwise every request carries the proxy's address.

## Whitelisted routes

`whitelist_routes` lists paths that skip **every** check, including the payload inspection. Use it for webhooks and other endpoints that a service calls with a scripted client. Patterns are matched with `fnmatch()` against the request path without a leading slash.

```php
'whitelist_routes' => [
    'api/mollie-webhook',
    'api/webhooks/*',
],
```

The two defaults are examples; replace them with your own paths. Routes that live outside the `web` middleware group, such as everything in `routes/api.php`, are not covered by the middleware in the first place.

## The response

```php
'response_status' => 403,
'response_message' => 'Access Denied',
```

The body is sent as plain text. `404` is a common alternative: a bot that gets "Not Found" has no reason to try again.

## Logging

With `log_blocked_requests` enabled (the default), every blocked request writes a warning to the default log channel:

```
[LivewireInjectionStopper] Blocked User-Agent: python-requests/2.31.0 {"reason":"blocked_user_agent","ip":"203.0.113.42","user_agent":"python-requests/2.31.0","url":"https://example.com/contact","method":"POST"}
```

The messages are `Blocked IP: …`, `Blocked User-Agent: …`, `Suspicious Livewire payload detected` and `Blocked Livewire property manipulation attempt`. They stay the same between releases so log alerts keep matching.

## The `RequestBlocked` event

`Darvis\LivewireInjectionStopper\Events\RequestBlocked` is dispatched for every blocked request and every silenced exception, whether logging is on or off. It carries:

| Property | Value |
| --- | --- |
| `reason` | `blocked_ip`, `blocked_user_agent`, `suspicious_payload` or `locked_property`, also available as constants on the class |
| `ip` | the client IP, or null |
| `userAgent` | the User-Agent header, or null |
| `url` | the full URL of the request |
| `exception` | the silenced exception, or null for middleware blocks |

```php
use Darvis\LivewireInjectionStopper\Events\RequestBlocked;
use Illuminate\Support\Facades\Event;

Event::listen(RequestBlocked::class, function (RequestBlocked $event) {
    if ($event->reason === RequestBlocked::BLOCKED_IP) {
        return;
    }

    Cache::increment('blocked-requests:'.now()->format('Y-m-d'));
});
```

## Applying the middleware elsewhere

The middleware is pushed onto the `web` group automatically. For another group or a single route, use the alias:

```php
Route::middleware('livewire-injection-stopper')->group(function () {
    // ...
});
```

There is no master switch. To turn the filter off without removing the package, empty `blocked_user_agents` and `blocked_ips` and set `check_payload_injection` to false.

## Checking a request yourself

The `LivewireInjectionStopper` facade exposes the manager. `check($request)` returns the reason or null; the individual checks are there too:

```php
use Darvis\LivewireInjectionStopper\Facades\LivewireInjectionStopper;

LivewireInjectionStopper::isBlockedUserAgent($request->userAgent());
LivewireInjectionStopper::isBlockedIp($request->ip());
LivewireInjectionStopper::isWhitelisted($request->path());
LivewireInjectionStopper::hasSuspiciousPayload($request);
```
