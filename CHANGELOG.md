# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2026-01-03

### Added

- **Livewire Payload Injection Detection** - Detects and blocks attempts to inject arrays into scalar Livewire properties (e.g., boolean properties like `is_admin`, `has_access`)
- New config option `check_payload_injection` to enable/disable payload checking

### Changed

- **Expanded blocked User-Agent list** - Added more HTTP clients and unwanted bots:
  - HTTP clients: `aiohttp`, `httpx`, `go-http-client`, `java/`, `okhttp`, `axios`, `node-fetch`, `libwww-perl`, `python-urllib`
  - SEO/AI bots: `ahrefsbot`, `semrushbot`, `dotbot`, `mj12bot`, `blexbot`, `dataforseo`, `bytespider`, `petalbot`, `gptbot`, `claudebot`, `ccbot`, `anthropic`
- Improved `curl` pattern matching (now `curl/` to be more specific)

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
