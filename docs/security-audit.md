---
title: Security audit
nav_order: 5
description: What php artisan livewire-injection-stopper:audit scans, which Livewire properties it flags as CRITICAL, HIGH or MEDIUM, how to fix them with #[Locked], and its limits.
---

# Security audit

Every public property of a Livewire component can be changed from the browser unless it is `#[Locked]`. A `public bool $isAdmin = false` is one request away from `true`. The audit command finds those properties.

```bash
php artisan livewire-injection-stopper:audit
```

## What it scans

The command reads every `.php` file under `app/Livewire` and `app/Traits`. A file counts when it contains `extends Component`, or `trait ` together with `Trait` in its contents. Files elsewhere are not scanned.

In those files it looks, line by line, for public typed properties with a default value:

```php
public bool $isAdmin = false;
public int $maxItems = 10;
public string $role = 'user';
public ?User $user = null;
```

The property is flagged when the line directly above it does not contain `#[Locked]` and one of these holds:

- The name contains `admin`, `role`, `permission`, `auth`, `max`, `min`, `limit`, `redirect`, `available`, `allowed`, `cart`, `user`, `client`, `model`, `locale`, `config` or `setting`.
- The type is a nullable class (`?User`, `?Cart`).
- The type is `bool`, unless the name is `checked`, `selected`, `enabled` or `visible`.

## The output

```
🔍 Scanning Livewire components for security issues...

⚠️  Potential vulnerabilities found:

[CRITICAL]
  📍 app/Livewire/Checkout.php:14
     Property: $isAdmin (bool)
     💡 Add #[Locked] attribute above this property

[HIGH]
  📍 app/Livewire/Cart.php:12
     Property: $maxQuantity (int)
     💡 Add #[Locked] attribute above this property

Total: 2 vulnerable properties found
```

| Severity | Name contains |
| --- | --- |
| CRITICAL | `admin`, `role`, `permission`, `auth`, `isadmin` |
| HIGH | `max`, `limit`, `user`, `client`, `cart` |
| MEDIUM | everything else that was flagged |

A file with public properties but without `use Livewire\Attributes\Locked` gets a warning as well, even when nothing was flagged.

The exit code is `1` when at least one property was flagged and `0` otherwise, so the command can run in CI:

```yaml
- run: php artisan livewire-injection-stopper:audit
```

## Fixing a finding

Add `#[Locked]` on the line directly above the property. Livewire then throws `CannotUpdateLockedPropertyException` when a request tries to change it, and this package answers that with the block response.

```php
use Livewire\Attributes\Locked;

class Checkout extends Component
{
    #[Locked]
    public bool $isAdmin = false;

    #[Locked]
    public float $price = 100.00;
}
```

If a flagged property really must be editable from the browser (`public bool $visible = true` is a common one), validate its value in the action that uses it, and leave it. The audit will keep listing it; it has no ignore list.

## Limits

- It is a text scan of single lines, not static analysis. A property declared without a type, without a default value, or across several lines is not seen.
- `#[Locked]` must be on the line directly above the property. `#[Locked] public bool $x = false;` on one line is not recognised.
- It flags names, not data flow. A `public float $price` is not flagged, because `price` is not in the list. Add `#[Locked]` to every property that a user must not change, whether the audit mentions it or not.
- Components outside `app/Livewire`, such as those in a module or a package, are not scanned.
