# Changelog

All notable changes to **darvis/livewire-injection-stopper** are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.3.0] - 2026-09-18

**On 1.2.3? Update.** That version stopped the reporting of every exception in the application, so Sentry, Flare and the log received nothing at all. See Fixed.

Upgrading is `composer update darvis/livewire-injection-stopper`. The config keys, their defaults and the block response are unchanged, and a published config file keeps working. Only an application that extended `BlockInjectionAttempts` to override one of its protected methods has work to do; see Changed.

### Added
- Laravel 13 and Livewire 4 support. CI tests PHP 8.2 to 8.4 with Laravel 11, 12 and 13, on the lowest and the latest dependencies
- `RequestBlocked` event, dispatched for every blocked request and every silenced exception, with `reason`, `ip`, `userAgent`, `url` and `exception`. The reasons are `blocked_ip`, `blocked_user_agent`, `suspicious_payload` and `locked_property`
- `LivewireInjectionStopperManager::check()` runs the checks in order and returns the reason; `reject()` is the single rejection path that logs, dispatches the event and builds the response. Both are available through the `LivewireInjectionStopper` facade, as are the config accessors
- The Livewire update endpoint is recognised by its route name (ending in `livewire.update`, as in Livewire 3 and 4) as well as by the path `livewire/update`, so a custom Livewire update URI is inspected too
- Documentation site at [arviddejong.github.io/livewire-injection-stopper](https://arviddejong.github.io/livewire-injection-stopper/) with `llms.txt`, a Laravel Boost guideline and skill in `resources/boost/`, `SECURITY.md`, issue forms and a pull request template

### Changed
- `livewire/livewire` (3 or 4) is a runtime requirement instead of a dev dependency; the exception silencing always needed it
- PHP 8.2 is the minimum. PHP 8.1 was listed, but Laravel 11 already required 8.2
- `BlockInjectionAttempts` delegates every check to `LivewireInjectionStopperManager` and no longer has protected helper methods. An override of one of them in a subclass is no longer reached, silently. `LivewireInjectionStopperManager` is no longer `final` and is now the extension point: extend it and bind the subclass with `$this->app->singleton(LivewireInjectionStopperManager::class, MyManager::class)`. The middleware keeps its manager in a `protected` property
- The block response for a silenced exception is produced by the middleware instead of a `renderable()` callback; the response, the log line and the event are the same as before
- The log context of a blocked request now includes `reason`; the log messages are unchanged
- The config file moved from `src/config` to `config`. Publishing works as before
- The config keys are in alphabetical order. `response_status` and `response_message` have a comment block each. No key, default or behaviour changed
- Larastan runs at level 8, Pint uses the plain Laravel preset, and the tests no longer override the config defaults

### Fixed
- **Since 1.2.3, the package stopped the reporting of every exception in the application.** Its `reportable()` callback handled every `Throwable` and was registered with `->stop()`, which makes Laravel end the reporting after that callback for all exceptions, not only the silenced ones. Callbacks registered in `withExceptions()` (Sentry, Flare) and the log never received anything. The callback now only stops the two silenced exceptions
- On Livewire 4 the payload inspection never ran, because Livewire 4 serves its update endpoint at `/livewire-<hash>/update` and the middleware only knew the path `livewire/update`. The endpoint is now recognised by its route name
- On Livewire 4 outside debug mode, a locked-property attempt was answered by Livewire's own empty 419 instead of the block response, without a log line or event, because that exception renders itself before any `renderable()` callback runs. The middleware now replaces the rendered response, whoever rendered it
- `allowed_user_agents` was honoured by the facade but not by the middleware, so a whitelisted uptime monitor that matched a blocked pattern was still blocked
- The README and the docs listed `python-requests`, `curl`, `bot`, `spider` and `crawler` as default patterns; the actual defaults are `python`, `curl/` and named crawlers, and generic words are left out on purpose

## [1.2.3] - 2026-02-20
### Added
- Silent handling for Livewire bot-driven `TypeError` exceptions (for example: `Cannot assign array to property ...`) when they originate from Livewire update flows
- Payload update normalization for multiple Livewire request formats, improving detection of array-injection attempts

### Changed
- Exception silencing now integrates with `dontReport`, `reportable`, and `renderable` hooks for stronger compatibility with Laravel exception pipelines
- Improved local warning message text for blocked Livewire manipulation attempts
- Updated documentation to clarify Sentry/noise behavior, payload-injection handling, and custom `app/Exceptions/Handler.php` integration guidance

## [1.2.2] - 2026-01-05
### Changed
- Simplified `blocked_user_agents` config by using single `'python'` pattern instead of multiple variants
- Added documentation explaining that patterns use `str_contains()` for wildcard matching
- Removed redundant patterns: `python-requests`, `python/requests`, `python requests`, `python requests 2.`, `python-urllib` (all now covered by `'python'`)

## [1.2.1] - 2026-01-05
### Changed
- Added explicit pattern `'python requests 2.'` to blocked user agents for better detection of Python Requests 2.x versions

## [1.2.0] - 2026-01-04
### Added
- **Sentry Error Silencing** - Automatically silences `CannotUpdateLockedPropertyException` errors from being reported to Sentry and other error tracking services
- New `SilentExceptionHandler` class to handle locked property exceptions without reporting them
- New config option `silence_locked_property_exceptions` to enable/disable Sentry error silencing (enabled by default)
- Exception handling registration in ServiceProvider to catch and return 403 responses for locked property manipulation attempts

### Changed
- Updated README.md with new Sentry error silencing feature documentation
- ServiceProvider now registers exception handling for locked property exceptions

## [1.1.1] - 2026-01-03
### Added
- **Livewire Payload Injection Detection** - Detects and blocks attempts to inject arrays into scalar Livewire properties (type confusion attacks)
- **User-Agent Whitelist** - New `allowed_user_agents` config for monitoring tools (Sentry Uptime, UptimeRobot, Pingdom, StatusCake)
- **Block All Array Injections** - New `block_all_array_injections` config option to block arrays sent to top-level properties
- **Known Scalar Properties List** - New `scalar_properties` config with common property names that should never receive arrays
- New config option `check_payload_injection` to enable/disable payload checking

### Changed
- **Expanded blocked User-Agent list** - Added more HTTP clients and unwanted bots:
  - HTTP clients: `aiohttp`, `httpx`, `go-http-client`, `java/`, `okhttp`, `axios`, `node-fetch`, `libwww-perl`, `python-urllib`
  - SEO/AI bots: `ahrefsbot`, `semrushbot`, `dotbot`, `mj12bot`, `blexbot`, `dataforseo`, `bytespider`, `petalbot`, `gptbot`, `claudebot`, `ccbot`, `anthropic`
- Improved `curl` pattern matching (now `curl/` to be more specific)
- Improved `looksLikeScalarProperty()` method to also check exact property names and block arrays to non-nested properties
- Code style improvements (removed verbose PHPDoc blocks, consistent spacing)

## [1.0.0] - 2026-01-03
### Added
- **Spam Bot Blocking Middleware** - Automatically blocks automated spam bots (Python scripts, curl, wget, scrapy) from accessing your application
- **Livewire Security Audit Command** - `php artisan livewire-injection-stopper:audit` scans Livewire components for vulnerable public properties
- **IP Blocking** - Block specific IP addresses via configuration
- **User-Agent Blocking** - Block requests based on User-Agent patterns
- **Route Whitelisting** - Whitelist specific routes (e.g., webhooks) from bot blocking
- **Suspicious Payload Detection** - Detects and blocks suspicious Livewire update payloads
- **Configurable Settings** - Publish and customize all blocking rules via config file
- Support for PHP 8.1, 8.2, and 8.3
- Support for Laravel 11 and 12
- Support for Livewire 3

[Unreleased]: https://github.com/ArvidDeJong/livewire-injection-stopper/compare/v1.3.0...HEAD
[1.3.0]: https://github.com/ArvidDeJong/livewire-injection-stopper/compare/v1.2.3...v1.3.0
[1.2.3]: https://github.com/ArvidDeJong/livewire-injection-stopper/compare/v1.2.2...v1.2.3
[1.2.2]: https://github.com/ArvidDeJong/livewire-injection-stopper/compare/v1.2.1...v1.2.2
[1.2.1]: https://github.com/ArvidDeJong/livewire-injection-stopper/compare/v1.2.0...v1.2.1
[1.2.0]: https://github.com/ArvidDeJong/livewire-injection-stopper/compare/v1.1.1...v1.2.0
[1.1.1]: https://github.com/ArvidDeJong/livewire-injection-stopper/compare/v1.1.0...v1.1.1
[1.0.0]: https://github.com/ArvidDeJong/livewire-injection-stopper/releases/tag/v1.0.0
