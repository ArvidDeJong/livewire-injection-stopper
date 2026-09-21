---
name: livewire-injection-stopper-development
description: Work with darvis/livewire-injection-stopper. Use it to tune bot blocking and Livewire payload inspection, fix audit findings with #[Locked], keep bot-driven Livewire exceptions out of Sentry, and test blocked requests.
---

# darvis/livewire-injection-stopper development

## When to use this skill

Use this skill when a request is unexpectedly blocked in an application that has `darvis/livewire-injection-stopper` installed, when `php artisan livewire-injection-stopper:audit` fails in CI, when Sentry still shows `CannotUpdateLockedPropertyException`, or when you write tests around blocked requests.

## How a request is checked

`BlockInjectionAttempts` runs on the `web` group and asks `LivewireInjectionStopperManager::check()`. The checks run in this order and stop at the first hit:

| Step | Check | Config keys | `RequestBlocked` reason |
| --- | --- | --- | --- |
| 1 | Path matches `whitelist_routes` (`fnmatch`, no leading slash): the request passes | `whitelist_routes` | none |
| 2 | Client IP is listed | `blocked_ips` | `blocked_ip` |
| 3 | User-Agent contains a blocked pattern and none of the allowed patterns (case-insensitive `str_contains`) | `blocked_user_agents`, `allowed_user_agents` | `blocked_user_agent` |
| 4 | Livewire update request (route name ending in `livewire.update`, or path `livewire/update`) sends an array to a property that looks scalar | `check_payload_injection`, `block_all_array_injections`, `scalar_properties` | `suspicious_payload` |

A property looks scalar when its name starts with `is_`, `has_`, `show_`, `can_`, `should_`, `enable`, `disable`, `active`, `visible` or `hidden`, is listed in `scalar_properties`, or (with `block_all_array_injections`) has no dot in its name. The prefixes and `scalar_properties` are compared with the whole name, so `form.tags` and `form.email` pass, while `activeFilters.tags` is blocked by its prefix. Entries in `scalar_properties` must be lowercase.

Every rejection goes through `reject()`: a log warning (unless `log_blocked_requests` is false), the `RequestBlocked` event, then a response built from `response_status` and `response_message`.

Separately, `CannotUpdateLockedPropertyException` and Livewire array-assignment `TypeError`s are marked as not reportable, and the middleware replaces their rendered error response with the same block response, reason `locked_property`. The reporting hooks are registered at boot from `silence_locked_property_exceptions`. Outside debug mode Livewire 4 (checked against 4.4) answers wrong-type values itself with `abort(419)` before a `TypeError` reaches Laravel; that passes through unchanged, without a log line or event.

## A legitimate client is blocked

- **Uptime monitor or search engine**: add a substring of its User-Agent to `allowed_user_agents`. The allowed list wins. Never remove entries from `blocked_user_agents` just to let one client through.
- **Webhook or API endpoint called by a script**: add the path to `whitelist_routes`. Routes outside the `web` group are not covered by the middleware anyway.
- **A multi-select or checkbox group**: the component binds an array at the top level. Bind it under a nested key (a form object, `form.tags`) or set `block_all_array_injections` to false and keep `scalar_properties` to the names your components use.

```php
// config/livewire-injection-stopper.php
'allowed_user_agents' => ['uptimerobot', 'pingdom', 'statuscake', 'sentryuptimebot', 'betteruptime'],
'whitelist_routes' => ['api/webhooks/*', 'stripe/webhook'],
'block_all_array_injections' => false,
```

## Fixing an audit finding

The audit scans `app/Livewire` and `app/Traits` for lines like `public bool $isAdmin = false;` without `#[Locked]` on the line directly above. Add the attribute; don't rename the property.

```php
use Livewire\Attributes\Locked;

#[Locked]
public bool $isAdmin = false;
```

A property that must stay editable from the browser is validated in the action that uses it and left as is; the audit has no ignore list, so the CI step then needs `|| true` or a separate job.

## Sentry still reports the exception

The package hooks `dontReport()` and `reportable()` on Laravel's exception handler; the block response comes from the middleware, not from a `renderable()` callback. A handler that overrides `report()` and calls `captureException()` directly bypasses that:

```php
use Darvis\LivewireInjectionStopper\Exceptions\SilentExceptionHandler;

public function report(Throwable $e): void
{
    if (SilentExceptionHandler::shouldSilence($e)) {
        return;
    }

    parent::report($e);
}
```

## Testing

Laravel's HTTP test client sends the User-Agent `Symfony`, which is not blocked, and `Livewire::test()` runs its requests with all middleware disabled, so existing tests keep passing. That also means a component test does not show that a top-level array update is blocked in the browser.

```php
use Darvis\LivewireInjectionStopper\Events\RequestBlocked;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;

Event::fake([RequestBlocked::class]);

$this->get('/contact', ['User-Agent' => 'python-requests/2.31.0'])->assertForbidden();

// Livewire 3: /livewire/update, Livewire 4: /livewire-<hash>/update. Find it by name, send a CSRF token.
$uri = collect(Route::getRoutes()->getRoutes())
    ->first(fn ($route) => str_ends_with((string) $route->getName(), 'livewire.update'))
    ->uri();

$this->withSession(['_token' => 'csrf'])
    ->postJson('/'.$uri, ['components' => [['updates' => ['title' => ['x']]]]], ['X-CSRF-TOKEN' => 'csrf'])
    ->assertForbidden();

Event::assertDispatched(RequestBlocked::class, fn (RequestBlocked $e) => $e->reason === RequestBlocked::SUSPICIOUS_PAYLOAD);
```

Change options with `config(['livewire-injection-stopper.blocked_user_agents' => ['bot']])` inside the test; only `silence_locked_property_exceptions` needs to be set before the application boots. Bypass everything with `$this->withoutMiddleware(BlockInjectionAttempts::class)`.
