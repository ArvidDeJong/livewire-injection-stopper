# Installation Guide

## Requirements

- PHP 8.1 or higher
- Laravel 11.0 or 12.0
- Livewire 3.0 (for audit command)

## Installation via Composer

Install the package using Composer:

```bash
composer require darvis/livewire-injection-stopper
```

The package will automatically register itself via Laravel's package discovery.

## Publishing Configuration

Optionally, publish the configuration file to customize settings:

```bash
php artisan vendor:publish --tag=livewire-injection-stopper-config
```

This creates `config/livewire-injection-stopper.php` where you can customize:
- Blocked user agents
- Blocked IP addresses
- Whitelisted routes
- Response status codes
- Logging preferences

## Verification

Verify the installation by running the security audit:

```bash
php artisan livewire-injection-stopper:audit
```

The middleware is automatically applied to all `web` routes and will start blocking spam bots immediately.

## Next Steps

- [Configure the middleware](middleware-configuration.md)
- [Run the security audit](security-audit.md)
- [Learn about Livewire security](livewire-security.md)
