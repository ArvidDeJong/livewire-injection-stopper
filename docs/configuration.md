---
title: "Configuration"
nav_order: 8
description: "Every key in config/livewire-injection-stopper.php with its default value and effect, the publish command, and when a changed value takes effect."
---

# Configuration

The package works without a config file. To change a default, publish the file:

```bash
php artisan vendor:publish --tag=livewire-injection-stopper-config
```

That writes `config/livewire-injection-stopper.php`. A key you remove from the published file keeps the package default, but a list you keep replaces the default list completely: the entries are not merged.

The package reads no environment variables. If you want one, call `env()` in the published file yourself.

## Every key

The keys are in the order of the file, which is alphabetical.

| Key | Default | What it does |
| --- | --- | --- |
| `allowed_user_agents` | `sentryuptimebot`, `uptimerobot`, `pingdom`, `statuscake` | Substrings that let a User-Agent pass the User-Agent check, case-insensitive. Checked before the blocked list. |
| `block_all_array_injections` | `true` | Reject an array sent to any top-level Livewire property, not only to the known names and prefixes. See [Payload injection](payload-injection.md#which-updates-are-rejected). |
| `blocked_ips` | `[]` | Exact IP addresses to block. No ranges. |
| `blocked_user_agents` | 27 patterns, see [Bot blocking](bot-blocking.md#which-user-agents-are-blocked) | Substrings that block a User-Agent, case-insensitive. |
| `check_payload_injection` | `true` | Inspect Livewire update requests for arrays sent to properties that look scalar. `false` turns the whole inspection off. |
| `log_blocked_requests` | `true` | Write a warning to the default log channel for every blocked request. The `RequestBlocked` event is dispatched either way. |
| `response_message` | `Access Denied` | Body of the block response. |
| `response_status` | `403` | HTTP status of the block response. |
| `scalar_properties` | 33 names, see [Payload injection](payload-injection.md#which-updates-are-rejected) | Property names that never hold an array. Write them in lowercase. |
| `silence_locked_property_exceptions` | `true` | Answer `CannotUpdateLockedPropertyException` and Livewire's array-assignment `TypeError` with the block response, and keep them out of Laravel's exception reporting. |
| `whitelist_routes` | `api/mollie-webhook`, `api/webhooks/*` | `fnmatch()` patterns for request paths, without a leading slash, that skip every check. The defaults are examples. |

## When a change takes effect

All keys are read on every request, with one exception. The reporting hooks behind `silence_locked_property_exceptions` are registered when the package boots. Setting that key with `config([...])` later in the request, or in a test, changes whether the response is replaced but not whether the exception is reported.

If your application caches its config (`php artisan config:cache`), run that command again after every change to the file, or run `php artisan config:clear`. Until then Laravel keeps using the old values.

## Reading the config in code

`LivewireInjectionStopperManager` is the only class in the package that reads these values. It exposes them as methods: `allowedUserAgents()`, `blockedIps()`, `blockedUserAgents()`, `blocksAllArrayInjections()`, `checksPayloadInjection()`, `logsBlockedRequests()`, `responseMessage()`, `responseStatus()`, `scalarProperties()`, `silencesLockedPropertyExceptions()` and `whitelistedRoutes()`.

```php
<?php

use Darvis\LivewireInjectionStopper\LivewireInjectionStopperManager;

$status = app(LivewireInjectionStopperManager::class)->responseStatus(); // 403
```

Entries of a list that are not strings are ignored.
