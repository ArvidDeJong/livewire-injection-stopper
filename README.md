# Livewire Injection Stopper

**Protect your Laravel + Livewire application from spam bots and security vulnerabilities.**

[![Latest Version](https://img.shields.io/packagist/v/darvis/livewire-injection-stopper.svg)](https://packagist.org/packages/darvis/livewire-injection-stopper)
[![License](https://img.shields.io/packagist/l/darvis/livewire-injection-stopper.svg)](https://packagist.org/packages/darvis/livewire-injection-stopper)

## What does this package do?

This package protects your Laravel application in three ways:

### 1. 🛡️ Blocks Spam Bots

Automatically blocks automated spam bots (like Python scripts, curl, wget) from accessing your website. No more spam form submissions!

### 2. 🔍 Finds Security Holes in Livewire

Scans your Livewire components and tells you which properties attackers could manipulate. For example, if you have `public $isAdmin = false`, an attacker could change it to `true` in their browser!

### 3. 🔇 Silences Sentry Errors from Bot Attacks

When bots manipulate Livewire payloads, they can trigger `CannotUpdateLockedPropertyException` or Livewire property-assignment `TypeError` exceptions. This package silently handles those bot-driven exceptions and prevents them from being reported to Sentry or other error tracking services, keeping your error logs clean.

## Installation

```bash
composer require darvis/livewire-injection-stopper
```

That's it! The spam bot blocking is now active.

## Check Your Security

Run this command to scan your Livewire components:

```bash
php artisan livewire-injection-stopper:audit
```

It will show you which properties need protection.

## Example: Fixing a Security Issue

**Before (Vulnerable):**
```php
class CheckoutComponent extends Component
{
    public $price = 100.00;  // ⚠️ Attacker can change this to $0.01!
}
```

**After (Secure):**
```php
use Livewire\Attributes\Locked;

class CheckoutComponent extends Component
{
    #[Locked]  // ✅ Now protected!
    public $price = 100.00;
}
```

## What Gets Blocked?

By default, these bots are blocked:
- Python scripts (`python-requests`)
- Command-line tools (`curl`, `wget`)
- Web scrapers (`scrapy`)
- Generic bots and crawlers

Real browsers and users are never blocked.

## Configuration (Optional)

Want to customize? Publish the config file:

```bash
php artisan vendor:publish --tag=livewire-injection-stopper-config
```

Now you can:
- Add or remove blocked bots
- Block specific IP addresses
- Whitelist certain routes (like webhooks)
- Enable/disable Sentry error silencing

## Sentry Error Silencing

By default, this package silences bot-driven Livewire update exceptions, including:
- `CannotUpdateLockedPropertyException`
- Livewire property assignment `TypeError` exceptions (for example: `Cannot assign array to property ...`)

This keeps your Sentry error logs clean.

**How it works:**
- Middleware blocks suspicious Livewire update payloads before component assignment when possible
- If Livewire still throws a protected-property or array-assignment exception, this package catches it and returns a 403 response
- The exception is logged locally (if logging is enabled) but NOT sent to Sentry

### Important: Custom Exception Handlers

If your app overrides `report()` in `app/Exceptions/Handler.php` and directly calls Sentry (`captureException`), make sure you skip reporting when `SilentExceptionHandler::shouldSilence($exception)` returns `true`. Otherwise, your custom handler can bypass package silencing.

**To disable this feature:**
```php
// config/livewire-injection-stopper.php
'silence_locked_property_exceptions' => false,
```

## Documentation

For detailed documentation, see the [`/docs`](docs/README.md) folder:

- **[Installation Guide](docs/installation.md)** - Detailed setup instructions
- **[Security Audit](docs/security-audit.md)** - How to use the audit command
- **[Middleware Configuration](docs/middleware-configuration.md)** - Customize bot blocking
- **[Livewire Security](docs/livewire-security.md)** - Understanding the threats
- **[Testing](docs/testing.md)** - Running tests

## Quick Links

- 📖 [Full Documentation](docs/README.md)
- 🐛 [Report Issues](https://github.com/darvis/livewire-injection-stopper/issues)
- 💬 [Get Support](mailto:info@arvid.nl)

## Requirements

- PHP 8.1+
- Laravel 11.0 or 12.0
- Livewire 3.0

## License

MIT License - feel free to use in any project!

## Credits

Created by [Arvid de Jong](mailto:info@arvid.nl)

---

**Need help?** Check the [documentation](docs/README.md) or email info@arvid.nl
