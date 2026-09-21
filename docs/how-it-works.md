---
title: "How it works"
nav_order: 4
description: "The four checks darvis/livewire-injection-stopper runs on a web request, in order, what each one looks at, what a block does, and what the package does not stop."
---

# How it works

The package has three parts: a request filter, the silencing of two Livewire exceptions, and an audit command. They work independently.

## Which checks run on a request

`BlockInjectionAttempts` is appended to the `web` middleware group when the package boots. It runs on every request that goes through that group, including Livewire's update requests. It asks `LivewireInjectionStopperManager::check()` for a verdict. The checks run in this order and stop at the first hit:

| Step | What it looks at | Config keys | Reason when blocked |
| --- | --- | --- | --- |
| 1 | The request path without a leading slash, matched with `fnmatch()` against `whitelist_routes`. On a match the request passes and steps 2 to 4 are skipped. | `whitelist_routes` | none |
| 2 | `$request->ip()`, compared exactly with each listed address. | `blocked_ips` | `blocked_ip` |
| 3 | The `User-Agent` header, lowercased. It passes when it contains an allowed pattern; otherwise it is blocked when it contains a blocked pattern. | `allowed_user_agents`, `blocked_user_agents` | `blocked_user_agent` |
| 4 | Only on Livewire's update endpoint: the JSON body. It is blocked when a property update has an array as value and a property name that looks scalar. | `check_payload_injection`, `block_all_array_injections`, `scalar_properties` | `suspicious_payload` |

These four are the only checks on an incoming request. There is no rate limiting, no check of headers other than `User-Agent`, no check of cookies, query strings or form fields, and no inspection of values that are not arrays.

Step 4 recognises Livewire's update endpoint by its route name, which ends in `livewire.update` in Livewire 3 (`/livewire/update`) and Livewire 4 (`/livewire-<hash>/update`), or by a path that contains `livewire/update`. Other request bodies are never parsed. The rules for step 4 are on [Payload injection](payload-injection.md).

### Where the middleware sits

The middleware is added at the end of the `web` group, so Laravel's session and CSRF middleware run before it. A POST without a valid CSRF token is answered by Laravel with a 419 before this package sees it.

Routes outside the `web` group, such as everything in `routes/api.php`, are not filtered. Add the alias `livewire-injection-stopper` to them if you want that; see [Bot blocking](bot-blocking.md#applying-the-middleware-elsewhere).

## What happens when a request is blocked

Every rejection goes through one method, `LivewireInjectionStopperManager::reject()`:

1. When `log_blocked_requests` is true, a warning goes to the default log channel. The message is one of these, prefixed with `[LivewireInjectionStopper] `:

   | Reason | Log message |
   | --- | --- |
   | `blocked_ip` | `Blocked IP: ` followed by the address |
   | `blocked_user_agent` | `Blocked User-Agent: ` followed by the header |
   | `suspicious_payload` | `Suspicious Livewire payload detected` |
   | `locked_property` | `Blocked Livewire property manipulation attempt` |

   The context holds `reason`, `ip`, `user_agent`, `url` and `method`, plus `exception` (the class name) and `message` for a silenced exception.
2. `Darvis\LivewireInjectionStopper\Events\RequestBlocked` is dispatched, whether logging is on or off.
3. The response is built from `response_status` and `response_message`. The defaults are `403` and `Access Denied`.

The request never reaches your route or component.

## How the two Livewire exceptions are silenced

Some manipulated requests pass the filter and fail inside Livewire:

- Livewire throws `Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException` when a request tries to change a `#[Locked]` property.
- PHP throws a `TypeError` with a message containing `Cannot assign array to property` when Livewire assigns an array to a typed property.

With `silence_locked_property_exceptions` enabled (the default), two things happen for those exceptions, and for no others:

- The package registers them as not reportable on Laravel's exception handler, through `dontReport()` and a `reportable()` callback that returns `false`. Error trackers that receive exceptions through Laravel's reporting do not get them, and Laravel does not write its own error entry for them. The package writes its warning line instead. Every other exception is reported as usual.
- Laravel renders the exception inside the route pipeline and attaches it to the response. The middleware finds it there and replaces the response with the block response, through the same `reject()` as above, with the reason `locked_property`.

The details, including the `TypeError` trace check and Livewire 4's own 419 responses, are on [Payload injection](payload-injection.md#the-two-silenced-exceptions).

## What the audit command does

`php artisan livewire-injection-stopper:audit` reads the PHP files under `app/Livewire` and `app/Traits` and lists public typed properties with a default value whose name or type looks sensitive and that lack `#[Locked]` on the line above. It is a text scan; it changes nothing and it has no part in handling requests. See [Security audit](security-audit.md).

## Adding a check of your own

`LivewireInjectionStopperManager` is the extension point. Extend it, override the method you need, and bind the subclass in a service provider:

```php
<?php
// app/Providers/AppServiceProvider.php, inside register()

use App\Support\MyManager;
use Darvis\LivewireInjectionStopper\LivewireInjectionStopperManager;

$this->app->singleton(LivewireInjectionStopperManager::class, MyManager::class);
```

The middleware and the facade resolve the manager from the container, so one binding covers both. Do not extend `BlockInjectionAttempts` to override a check: the checks live in the manager, and an override on the middleware is never called.

## What it does not stop

- **A bot that sends a browser User-Agent.** The check matches a string the client chooses to send. It is not a web application firewall.
- **A request without a User-Agent.** An empty or missing header is never blocked by step 3.
- **IP ranges.** `blocked_ips` holds exact addresses. There is no CIDR or wildcard matching.
- **A manipulated scalar value.** A request that changes `price` from `100` to `0.01` sends a valid number. The package does not know which values are wrong. Use `#[Locked]` or validate the value; the audit command helps to find candidates.
- **Arrays sent under a nested key.** A property name with a dot, such as `form.tags` or `form.email`, passes, even though `email` is in `scalar_properties`: the list is compared with the whole name. The one exception is a name that starts with a fixed prefix, such as `activeFilters.tags`. Type nested properties or validate them.
- **Arrays when `block_all_array_injections` is off**, unless the property name is in `scalar_properties` or starts with one of the fixed prefixes.
- **Anything on a whitelisted path.** A whitelist match skips steps 2 to 4.
- **Anything outside the `web` group**, unless you add the middleware there.
- **What the audit cannot see.** The audit is a text scan of single lines, not static analysis. It flags names and types, not data flow, and it misses several ways to declare a property; see [its limits](security-audit.md#limits).

These are documented limits, not vulnerabilities; see [SECURITY.md](https://github.com/ArvidDeJong/livewire-injection-stopper/blob/main/SECURITY.md) for what does count.
