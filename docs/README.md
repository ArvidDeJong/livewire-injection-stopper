# Livewire Injection Stopper Documentation

Welcome to the Livewire Injection Stopper documentation. This package provides comprehensive security for your Laravel + Livewire applications.

## Quick Links

- [Installation Guide](installation.md) - Get started quickly
- [Middleware Configuration](middleware-configuration.md) - Configure bot blocking
- [Security Audit](security-audit.md) - Scan for vulnerabilities
- [Livewire Security](livewire-security.md) - Best practices and attack examples
- [Testing Guide](testing.md) - Run and write tests

## What This Package Does

### 🛡️ User-Agent & IP Blocking

Automatically blocks spam robots and malicious bots based on:
- User-Agent headers (Python Requests, curl, wget, etc.)
- IP addresses
- Configurable whitelist for legitimate API access

### 🔍 Livewire Security Audit

Scans your Livewire components for property injection vulnerabilities:
- Detects unprotected public properties
- Classifies severity (CRITICAL, HIGH, MEDIUM)
- Provides actionable fix recommendations
- Integrates with CI/CD pipelines

### 🔇 Exception Silencing for Bot Noise

Silences bot-driven Livewire exceptions from error trackers:
- `CannotUpdateLockedPropertyException`
- Livewire property assignment `TypeError` exceptions caused by malicious payloads

Returns a configurable block response while optionally logging locally.

## Getting Started

### 1. Install

```bash
composer require darvis/livewire-injection-stopper
```

### 2. Publish Config (Optional)

```bash
php artisan vendor:publish --tag=livewire-injection-stopper-config
```

### 3. Run Security Audit

```bash
php artisan livewire-injection-stopper:audit
```

## Documentation Structure

### For New Users

1. Start with [Installation](installation.md)
2. Read [Livewire Security](livewire-security.md) to understand the threats
3. Run the [Security Audit](security-audit.md)
4. Configure the [Middleware](middleware-configuration.md)

### For Developers

1. Review [Testing Guide](testing.md)
2. Understand [Livewire Security](livewire-security.md) patterns
3. Integrate audit into your CI/CD

### For Security Teams

1. Read [Livewire Security](livewire-security.md) for attack vectors
2. Review [Security Audit](security-audit.md) capabilities
3. Implement automated scanning

## Key Features

### Automatic Protection

The middleware is automatically applied to all `web` routes upon installation. No additional configuration required for basic protection.

### Custom Handler Compatibility

If your app has a custom `app/Exceptions/Handler.php` with manual Sentry reporting in `report()`, add a guard to skip reporting when the package marks an exception as silenced.

### Smart Detection

The security audit uses pattern matching to identify:
- Authorization flags (`$isAdmin`, `$canEdit`)
- Model instances (`User`, `Cart`)
- Business logic limits (`$maxQuantity`)
- Configuration values (`$locale`)

### Zero False Positives

The audit is designed to minimize false positives while catching real vulnerabilities. All detections include:
- File path and line number
- Property name and type
- Severity classification
- Fix recommendation

## Common Use Cases

### Protecting Forms from Spam

The middleware automatically blocks automated form submissions from bots.

### Preventing Price Manipulation

The security audit detects vulnerable price/quantity properties in e-commerce components.

### Securing Admin Panels

Identifies authorization flags that could be manipulated to gain admin access.

### API Protection

Whitelist legitimate API endpoints while blocking automated scrapers.

## Support

- **Email:** info@arvid.nl
- **Issues:** [GitHub Issues](https://github.com/darvis/livewire-injection-stopper/issues)
- **Security:** Report vulnerabilities privately to info@arvid.nl

## Contributing

Contributions are welcome! Please:
- Include tests for new features
- Follow PSR-12 coding standards
- Update documentation
- Run full test suite before submitting

## License

MIT License - see LICENSE file for details

## Credits

Developed by [Arvid de Jong](mailto:info@arvid.nl)
