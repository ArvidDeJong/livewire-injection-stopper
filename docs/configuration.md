---
title: Configuration
nav_order: 6
description: Every option in config/livewire-injection-stopper.php with its default, and the artisan command to publish the file.
---

# Configuration

The package works without a config file. To change anything, publish it:

```bash
php artisan vendor:publish --tag=livewire-injection-stopper-config
```

That writes `config/livewire-injection-stopper.php`. Values you don't set keep the package default.

| Key | Default | What it does |
| --- | --- | --- |
| `blocked_user_agents` | 27 patterns, see [Bot blocking](bot-blocking.md#user-agents) | Substrings that block a User-Agent, case-insensitive |
| `allowed_user_agents` | `sentryuptimebot`, `uptimerobot`, `pingdom`, `statuscake` | Substrings that always let a User-Agent through, checked before the blocked list |
| `blocked_ips` | `[]` | Exact IP addresses to block |
| `whitelist_routes` | `api/mollie-webhook`, `api/webhooks/*` | `fnmatch` patterns for paths that skip every check |
| `response_status` | `403` | HTTP status of the block response |
| `response_message` | `Access Denied` | Plain-text body of the block response |
| `log_blocked_requests` | `true` | Write a warning to the log for every blocked request |
| `check_payload_injection` | `true` | Inspect Livewire update requests for arrays sent to scalar properties |
| `block_all_array_injections` | `true` | Treat every top-level property as scalar, not only the known names and prefixes |
| `scalar_properties` | 33 names, see [Payload injection](payload-injection.md#the-payload-rules) | Property names that never hold an array |
| `silence_locked_property_exceptions` | `true` | Answer `CannotUpdateLockedPropertyException` and Livewire array-assignment `TypeError`s with the block response and keep them out of error tracking |

The reporting hooks behind `silence_locked_property_exceptions` are registered when the package boots, so that option must be set before the application boots. The other options are read on every request, so a change in a test with `config([...])` takes effect immediately.

## Reading the config in code

`LivewireInjectionStopperManager` is the only place in the package that reads these values, and it exposes them as methods: `blockedUserAgents()`, `allowedUserAgents()`, `blockedIps()`, `whitelistedRoutes()`, `scalarProperties()`, `responseStatus()`, `responseMessage()`, `logsBlockedRequests()`, `checksPayloadInjection()`, `blocksAllArrayInjections()` and `silencesLockedPropertyExceptions()`. Resolve it from the container or use the `LivewireInjectionStopper` facade.
