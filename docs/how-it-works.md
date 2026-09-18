---
title: How it works
nav_order: 2
description: The order in which darvis/livewire-injection-stopper checks a request, what happens when a request is blocked, and what the package does not stop.
---

# How it works

The package has three parts that work independently. Each one can be turned off in the [configuration](configuration.md).

## 1. The request filter

`BlockInjectionAttempts` is pushed onto the `web` middleware group when the package boots, so it runs on every web request, including Livewire's update requests. It asks `LivewireInjectionStopperManager::check()` for a verdict. The checks run in this order and stop at the first hit:

| Step | Check | Config keys | Reason when blocked |
| --- | --- | --- | --- |
| 1 | Is the path whitelisted? If so, the request passes without further checks. | `whitelist_routes` | none |
| 2 | Is the client IP listed? | `blocked_ips` | `blocked_ip` |
| 3 | Does the User-Agent contain a blocked pattern, and none of the allowed patterns? | `blocked_user_agents`, `allowed_user_agents` | `blocked_user_agent` |
| 4 | Is this a Livewire update request that sends an array to a property that should be scalar? | `check_payload_injection`, `block_all_array_injections`, `scalar_properties` | `suspicious_payload` |

Step 4 only runs on Livewire's update endpoint. It is recognised by its route name, which ends in `livewire.update` in Livewire 3 (`/livewire/update`) and Livewire 4 (`/livewire-<hash>/update`), or by the path `livewire/update`. Other POST bodies are never parsed.

## 2. The exception silencing

A payload can still reach Livewire, for example when the value is a string but the property is `#[Locked]`. Livewire then throws `CannotUpdateLockedPropertyException`. When a manipulated array does get assigned to a typed property, PHP throws a `TypeError` from inside Livewire's hydration.

Two things happen for those exceptions:

- The package marks them as not reportable on Laravel's exception handler, so Sentry, Flare and other trackers that hook into the reporting pipeline never see them. Every other exception is reported as usual.
- Laravel renders the exception inside the route pipeline and hands the rendered response back through the middleware. The middleware recognises the exception on that response and replaces it with the block response, logging and dispatching the event as for any other block. This works whoever rendered the exception: Livewire 4 gives its locked-property exception an own `render()` that answers with an empty 419 outside debug mode, and that would otherwise be the last word.

Livewire 4 also rejects wrong-type values itself during hydration with a 419, before a `TypeError` can occur. That response is not an exception the package sees, so it passes through unchanged and is not reported.

See [Payload injection](payload-injection.md#exception-silencing) for the details and the note on custom handlers.

## Extending the checks

`LivewireInjectionStopperManager` is the extension point. Extend it, override the check you need, and bind the subclass in a service provider:

```php
use Darvis\LivewireInjectionStopper\LivewireInjectionStopperManager;

$this->app->singleton(LivewireInjectionStopperManager::class, MyManager::class);
```

The middleware, the facade and the exception handling all resolve the manager from the container, so one binding covers all three. Do not extend `BlockInjectionAttempts` to override a check: the checks moved to the manager, and an override on the middleware is never called.

## What happens when a request is blocked

Every rejection goes through one method, `LivewireInjectionStopperManager::reject()`:

1. A warning is written to the default log channel when `log_blocked_requests` is true. The message names the reason (`Blocked User-Agent: curl/8.0`, `Blocked IP: …`, `Suspicious Livewire payload detected`, `Blocked Livewire property manipulation attempt`) and the context holds `reason`, `ip`, `user_agent`, `url`, `method` and, for silenced exceptions, `exception` and `message`.
2. `Darvis\LivewireInjectionStopper\Events\RequestBlocked` is dispatched with the same data.
3. The response is built from `response_status` and `response_message`. The defaults are `403` and `Access Denied`; some people prefer `404` to give bots nothing to work with.

## 3. The audit command

`php artisan livewire-injection-stopper:audit` is independent of the other two. It reads the PHP files under `app/Livewire` and `app/Traits` and reports public typed properties with a default value that look sensitive and lack `#[Locked]`. See [Security audit](security-audit.md).

## What it does not stop

- **A bot that sends a browser User-Agent.** The User-Agent check matches strings the client chooses to send. It removes the noise from scripts that don't bother, which is most of them; it is not a web application firewall.
- **A manipulated scalar value.** A request that changes `price` from `100` to `0.01` sends a valid number. The package cannot know that value is wrong; only `#[Locked]` can, and the audit command exists to find those properties.
- **Arrays sent under a nested key.** `form.tags` is never blocked, because that is how legitimate array properties are bound. Type those properties or validate them.
- **Anything on a whitelisted path.** A whitelist match skips every check, including the payload inspection.
- **What the audit cannot see.** The audit is a text scan of one line per property, not static analysis. It flags names, not data flow, and only sees properties with a type and a default value on the same line.

These are documented limits, not vulnerabilities; see [SECURITY.md](https://github.com/ArvidDeJong/livewire-injection-stopper/blob/main/SECURITY.md) for what does count.
