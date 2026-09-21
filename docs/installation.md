---
title: "Installation"
nav_order: 2
description: "Install darvis/livewire-injection-stopper with Composer, publish its config file, and check with curl and the audit command that the middleware is active."
---

# Installation

## Requirements

PHP 8.2+, Laravel 11, 12 or 13, and Livewire 3 or 4. Composer installs `livewire/livewire` if your application does not have it yet.

## Steps

1. Install the package:

   ```bash
   composer require darvis/livewire-injection-stopper
   ```

   Laravel discovers the service provider by itself. When the package boots it does three things:

   - it appends `Darvis\LivewireInjectionStopper\Middleware\BlockInjectionAttempts` to the `web` middleware group (the group Laravel applies to everything in `routes/web.php`, and the group Livewire's update route uses) and registers the alias `livewire-injection-stopper`;
   - it tells Laravel's exception handler not to report two bot-driven Livewire exceptions, see [Payload injection](payload-injection.md#the-two-silenced-exceptions);
   - it registers the command `php artisan livewire-injection-stopper:audit`.

2. Optional: publish the config file when you want to change a default.

   ```bash
   php artisan vendor:publish --tag=livewire-injection-stopper-config
   ```

   That writes `config/livewire-injection-stopper.php`. Every key is described on [Configuration](configuration.md).

3. Replace the two example paths in `whitelist_routes` (`api/mollie-webhook` and `api/webhooks/*`) with the webhook paths of your own application, or empty the list. A whitelisted path skips every check.

The package has no environment variables, no migration, no views and no JavaScript. There is nothing to add to `bootstrap/app.php`.

## Check that it works

### 1. The middleware blocks a scripted client

`curl` sends a User-Agent such as `curl/8.7.1`, which contains the default blocked pattern `curl/`. Request any page that lives in `routes/web.php`:

```bash
curl -i https://your-app.test/
```

Expect status `403` and this body:

```
Access Denied
```

With the default `log_blocked_requests`, `storage/logs/laravel.log` gets a line like this one:

```
[2026-09-21 10:45:11] local.WARNING: [LivewireInjectionStopper] Blocked User-Agent: curl/8.7.1 {"reason":"blocked_user_agent","ip":"127.0.0.1","user_agent":"curl/8.7.1","url":"https://your-app.test","method":"GET"}
```

A `200` with your page means the middleware did not run or did not match. See [Troubleshooting](troubleshooting.md#nothing-is-blocked).

### 2. The audit command runs

```bash
php artisan livewire-injection-stopper:audit
```

When nothing is flagged, the output is:

```
🔍 Scanning Livewire components for security issues...

✅ No security issues found!
```

The exit code is `0`. The same output appears when `app/Livewire` does not exist or holds no file the scan recognises, so a green result only means something when your components live there; see [Security audit](security-audit.md#what-it-scans).

When a property is flagged, the output starts with `⚠️  Potential vulnerabilities found:` and the exit code is `1`. [Security audit](security-audit.md#the-output) explains every line.

`Command "livewire-injection-stopper:audit" is not defined` means the package is not installed or Laravel's package discovery has not run; see [Troubleshooting](troubleshooting.md#the-audit-command-is-not-defined).

## Next

Continue with the [Quick start](quick-start.md), or read [How it works](how-it-works.md) first to see exactly which checks exist.
