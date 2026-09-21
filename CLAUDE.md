# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository. The conventions shared by every darvis package (language, releases, CI, docs site, Boost guidelines, public API policy) are in [../CLAUDE.md](../CLAUDE.md); this file only holds what is specific to this package.

## Package overview

`darvis/livewire-injection-stopper` is a Laravel package (PHP 8.2+, Laravel 11/12/13, Livewire 3/4) with three independent parts: a middleware that blocks scripted clients, listed IPs and manipulated Livewire payloads; exception handling that answers bot-driven Livewire exceptions with the block response and keeps them out of Sentry; and an artisan command that audits Livewire components for public properties that need `#[Locked]`. Host apps consume it via Composer; this repo only contains the library.

- Namespace: `Darvis\LivewireInjectionStopper\` → `src/`
- Service provider auto-registered via `extra.laravel.providers` in [composer.json](composer.json)
- Config key: `livewire-injection-stopper`, file in [config/](config/livewire-injection-stopper.php)

## Commands

```bash
composer test                 # Pest suite
vendor/bin/pest --filter "allowed user agents"
composer lint                 # Pint (check only); composer format fixes
composer analyse              # Larastan, level 8
```

## Architecture

- [LivewireInjectionStopperManager](src/LivewireInjectionStopperManager.php) is the only place that reads the package config, through named accessors (`blockedUserAgents()`, `responseStatus()`, …). Never call `config('livewire-injection-stopper.…')` elsewhere; before 1.3.0 the middleware had its own copy of every check and silently lacked the `allowed_user_agents` whitelist. `check()` holds the shared check order, `reject()` the single rejection path (log, `RequestBlocked` event, response). [BlockInjectionAttempts](src/Middleware/BlockInjectionAttempts.php) only calls those two. Don't add checks to the middleware.
- The log messages in `describe()` (`Blocked User-Agent: …`, `Blocked IP: …`, `Suspicious Livewire payload detected`, `Blocked Livewire property manipulation attempt`) are public API: site owners alert on them. Never reword them in a minor release; add context keys instead.
- The Livewire update endpoint is recognised by route name first (a name ending in `livewire.update`: Livewire 3 uses `default.livewire.update`, Livewire 4 `default-livewire.update`) and by the path `livewire/update` second. Keep both: Livewire 4 serves the endpoint at `/livewire-<hash>/update`, so before 1.3.0 the path check never matched there and the payload inspection was dead on Livewire 4. The name check only works after routing, which is fine because the middleware runs in the `web` group. `tests/Feature/MiddlewareTest.php` finds the route by name for the same reason.
- `looksLikeScalarProperty()` treats every top-level property as scalar while `block_all_array_injections` is true, so a top-level multi-select is rejected under the defaults. That is documented behaviour since 1.1.1; changing the default is a minor release with a CHANGELOG entry, not a quiet fix. Nested keys (with a dot) skip that rule on purpose; they are still blocked when the whole name starts with a scalar prefix (`activeFilters.tags`). `scalar_properties` is compared with the lowercased whole name, so its entries must be lowercase.
- [SilentExceptionHandler](src/Exceptions/SilentExceptionHandler.php) decides what is bot noise: `CannotUpdateLockedPropertyException`, and a `TypeError` whose message says `Cannot assign array to property` and whose trace passes through a `Livewire\` class or `vendor/livewire/livewire`. Never silence `TypeError` without the trace check; a `TypeError` from app code must still be reported. `livewire/livewire` is a runtime requirement because of this class.
- The block response for a silenced exception comes from the middleware, not from a `renderable()` callback: Laravel renders a controller exception inside the route pipeline and attaches it to the response (`$response->exception`), and the middleware replaces that response. A `renderable()` would lose against Livewire 4's own `render()` on `CannotUpdateLockedPropertyException`, which returns an empty 419 outside debug mode. Livewire 4 also answers wrong-type values with `abort(419)` itself; that is not a `TypeError`, so it passes through.
- The provider registers `dontReport()` and `reportable()` on the exception handler through `$app->resolving(ExceptionHandler::class)`, and applies them at once if the handler was already resolved. Both are looked up with `method_exists()`: in tests and under Octane, Collision wraps the handler in a decorator that forwards `reportable()` but is not a Foundation Handler and has no `dontReport()`. Never call `->stop()` on that `reportable()` callback: it handles every `Throwable`, and `stop()` makes `ReportableHandler` return false for all of them, which stops the reporting of every exception in the app after our callback. 1.2.3 shipped exactly that, so nothing reached Sentry or the log in apps that register their callbacks in `withExceptions()`. Returning `false` for the silenced exceptions is enough. The `silence_locked_property_exceptions` switch is read at boot for the hooks and per request by the middleware.
- The middleware is pushed onto the `web` group in `boot()`. That works because the HTTP kernel syncs its groups to the router before providers boot; don't move it to `register()`.
- [AuditLivewireSecurity](src/Console/Commands/AuditLivewireSecurity.php) is a line-by-line regex scan, not static analysis. It only sees `public <type> $name = …;` on one line with `#[Locked]` on the line directly above. Document limits rather than growing the regex; a real analysis belongs in a separate tool.
- Default `blocked_user_agents` names specific clients and crawlers and never contains `bot`, `spider` or `crawler`: those words would block Googlebot, bingbot and uptime monitors. Adding a default pattern is a minor release (someone's client gets blocked on `composer update`).

## Conventions

- Tests use the package defaults. Don't override the config in `TestCase::getEnvironmentSetUp()`; before 1.3.0 that hid the real defaults from `ConfigTest`. Set config inside the test that needs it. `silence_locked_property_exceptions` is the one exception: it needs its own test case class (`SilencingDisabledTest`) because the hooks are registered at boot. To assert that an exception is or isn't reported, register a `reportable()` spy after the package's callback and call `report()`; don't assert on `shouldReport()`, Collision's decorator doesn't forward `dontReport()`. Resolve the handler before `Livewire::test()`: Livewire 3's test harness rebinds it to an anonymous class without `reportable()`.
- Keep the public API compatible within 1.x: the middleware alias and its place in the `web` group, the manager's public methods and the facade, `SilentExceptionHandler::shouldSilence()` and `getDontReport()`, the `RequestBlocked` event and reason constants, the audit command and its exit codes, the config keys, the log messages.
- Releases follow the `release` skill.
