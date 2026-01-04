# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.0] - 2026-01-04

### Added

- **Sentry Error Silencing** - Automatically silences `CannotUpdateLockedPropertyException` errors from being reported to Sentry and other error tracking services
- New `SilentExceptionHandler` class to handle locked property exceptions without reporting them
- New config option `silence_locked_property_exceptions` to enable/disable Sentry error silencing (enabled by default)
- Exception handling registration in ServiceProvider to catch and return 403 responses for locked property manipulation attempts

### Changed

- Updated README.md with new Sentry error silencing feature documentation
- ServiceProvider now registers exception handling for locked property exceptions

---

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

---

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

[1.1.0]: https://github.com/darvis/livewire-injection-stopper/releases/tag/v1.1.0
[1.0.0]: https://github.com/darvis/livewire-injection-stopper/releases/tag/v1.0.0
