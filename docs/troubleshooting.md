---
title: "Troubleshooting"
nav_order: 10
description: "Symptom, cause and fix: a legitimate request gets Access Denied, nothing is blocked, Sentry still reports the exception, or the audit finds nothing."
---

# Troubleshooting

Start with the log. With the default settings every block writes a warning to the default log channel, in a default Laravel application `storage/logs/laravel.log`. Search for `[LivewireInjectionStopper]`. The `reason` in the context tells you which check rejected the request:

| Log message | `reason` | Check |
| --- | --- | --- |
| `[LivewireInjectionStopper] Blocked IP: …` | `blocked_ip` | `blocked_ips` |
| `[LivewireInjectionStopper] Blocked User-Agent: …` | `blocked_user_agent` | `blocked_user_agents` |
| `[LivewireInjectionStopper] Suspicious Livewire payload detected` | `suspicious_payload` | the payload rules |
| `[LivewireInjectionStopper] Blocked Livewire property manipulation attempt` | `locked_property` | a silenced exception |

The package has no migrations, no environment variables and no authentication guard, so those are never the cause. A cached config can be: after every change to `config/livewire-injection-stopper.php`, run `php artisan config:clear` (or `php artisan config:cache` again).

## A legitimate request is blocked

The visitor or client sees the block response: status `403` and the text `Access Denied`, unless you changed `response_status` or `response_message`.

### A Livewire form with checkboxes or a multi-select gets `Access Denied`

Log message: `Suspicious Livewire payload detected`.

Cause: the component binds an array to a top-level property, for example `wire:model="tags"`. With `block_all_array_injections` enabled (the default), every array sent to a top-level property is rejected.

Fix: bind the array under a nested key such as `form.tags`, or set `block_all_array_injections` to false. See [Components with array properties](payload-injection.md#components-with-array-properties).

### It is still blocked with `block_all_array_injections` set to false

Cause, one of:

- The property name is in `scalar_properties`. Remove it from the list.
- The property name starts with `is_`, `has_`, `show_`, `can_`, `should_`, `enable`, `disable`, `active`, `visible` or `hidden`, for example `activeFilters` or `hiddenColumns`. These prefixes are fixed in the code. Rename the property, or turn the inspection off with `check_payload_injection` set to false.
- The config is cached. Run `php artisan config:clear`.

### An uptime monitor, a search engine or your own script is blocked

Log message: `Blocked User-Agent: ` followed by the User-Agent.

Cause: the User-Agent contains one of the `blocked_user_agents` patterns. The defaults include HTTP libraries such as `python`, `curl/`, `okhttp`, `axios`, `java/` and `go-http-client`, which a mobile app, a server-side script or a monitoring tool may use.

Fix: add a distinctive part of that User-Agent to `allowed_user_agents`. The allowed list wins over the blocked list. See [Bot blocking](bot-blocking.md#let-a-client-through-with-the-allow-list).

### A webhook is blocked

Cause: the webhook route is in the `web` group and the calling service uses a scripted client.

Fix: add the path to `whitelist_routes`, without a leading slash, for example `stripe/webhook` or `webhooks/*`. A whitelisted path skips every check.

### Every visitor is blocked with `Blocked IP`

Cause: the application sits behind a proxy or load balancer, `$request->ip()` returns the proxy's address, and that address is in `blocked_ips`.

Fix: remove the address and configure Laravel's [trusted proxies](https://laravel.com/docs/requests#configuring-trusted-proxies).

## Nothing is blocked

### `curl` gets the normal page instead of `Access Denied`

Check these in order:

1. **The route is not in the `web` group.** The middleware is only added to `web`. Routes in `routes/api.php` are not filtered. Add the alias `livewire-injection-stopper` to them; see [Applying the middleware elsewhere](bot-blocking.md#applying-the-middleware-elsewhere).
2. **The path matches `whitelist_routes`.** A whitelisted path skips every check.
3. **Your published config file has no matching pattern.** A published list replaces the default list completely. Compare `config/livewire-injection-stopper.php` with `vendor/darvis/livewire-injection-stopper/config/livewire-injection-stopper.php`. A file published with an older version keeps its own lists (the default patterns changed in 1.1.1 and 1.2.2); publish again with `--force` and reapply your changes.
4. **The config is cached.** Run `php artisan config:clear`.
5. **The package is not installed in this environment.** `composer show darvis/livewire-injection-stopper` must list it.

### A bot still gets through

Cause, one of:

- It sends a browser User-Agent, or none at all. The User-Agent check cannot stop that; see [What it does not stop](how-it-works.md#what-it-does-not-stop).
- It sends scalar values, or arrays under a nested key. The payload rules only reject arrays sent to names that look scalar.

Fix: lock properties with `#[Locked]`, validate input, and add rate limiting or a honeypot for forms. This package does not replace those.

### A blocked IP address still gets through

Cause: behind a proxy, `$request->ip()` is the proxy's address, not the visitor's. Or the address is written as a range; `blocked_ips` only takes exact addresses.

Fix: configure Laravel's [trusted proxies](https://laravel.com/docs/requests#configuring-trusted-proxies), and block ranges in your firewall.

### A POST from a script gets a 419, not a 403

Cause: the middleware sits at the end of the `web` group. Laravel's CSRF check runs first and answers a POST without a valid token with 419. The request is stopped, but not by this package, so there is no log line and no event.

### A blocked request leaves no log line

Cause: `log_blocked_requests` is false, or your default log channel writes somewhere other than `storage/logs/laravel.log`. The `RequestBlocked` event is dispatched either way.

## Exceptions and error tracking

### Sentry still reports `CannotUpdateLockedPropertyException`

Cause, one of:

- `silence_locked_property_exceptions` is false, or was changed while the config is cached.
- Your exception handler overrides `report()` and calls the tracker directly. Add the early return from [Custom exception handlers](payload-injection.md#custom-exception-handlers).
- The tracker does not receive exceptions through Laravel's exception handler. The package only controls Laravel's reporting.

### No exception is reported at all since version 1.2.3

Cause: a bug in 1.2.3. Its `reportable()` callback stopped the reporting of every exception in the application.

Fix: `composer update darvis/livewire-injection-stopper` to 1.3.0 or later.

### A manipulated Livewire request gets an empty 419 instead of the block response

Cause: Livewire 4 answers a wrong-type value with `abort(419)` itself outside debug mode. That is not one of the two exceptions the package replaces, so the response passes through, without a log line or event. See [What Livewire 4 already answers itself](payload-injection.md#what-livewire-4-already-answers-itself).

### An override in a subclass of `BlockInjectionAttempts` is never called

Cause: since 1.3.0 every check lives in `LivewireInjectionStopperManager`.

Fix: extend the manager and bind your subclass; see [Adding a check of your own](how-it-works.md#adding-a-check-of-your-own).

## The audit command

### The audit command is not defined

Message: `Command "livewire-injection-stopper:audit" is not defined.`

Cause: the package is not installed, or Laravel's package discovery has not run.

Fix: run `composer require darvis/livewire-injection-stopper`, then `php artisan package:discover`. Check that `darvis/livewire-injection-stopper` is not listed under `extra.laravel.dont-discover` in your `composer.json`.

### `✅ No security issues found!` but there are unlocked properties

Cause: the audit is a text scan. It skips components outside `app/Livewire`, components that extend your own base class, properties without a default value and several types. The full list is under [Limits](security-audit.md#limits).

Fix: do not read a green audit as proof. Review the public properties of each component yourself.

### The audit fails CI for a property that must stay editable

Cause: the command exits with `1` for every finding, and it has no ignore list.

Fix: run the audit as a separate CI job that may fail, or append `|| true` to the step and read its output.
