---
title: "Security audit"
nav_order: 7
description: "What php artisan livewire-injection-stopper:audit scans, its literal output, the severity rules, how to fix a finding with #[Locked], and its limits."
---

# Security audit

A public property of a Livewire component can be changed from the browser unless it carries Livewire's [`#[Locked]`](https://livewire.laravel.com/docs/locked) attribute. A `public bool $isAdmin = false` is one request away from `true`. The audit command lists properties that look like that.

```bash
php artisan livewire-injection-stopper:audit
```

The command has no arguments and no options. It only reads files; it changes nothing.

## What it scans

The command reads every `.php` file under `app/Livewire` and `app/Traits`, including subfolders. A folder that does not exist is skipped without a message. A file is scanned when its text contains `extends Component`, or contains both `trait ` and `Trait`. Files elsewhere are not scanned.

In those files it looks, line by line, for an indented line that declares a public property with one of the types `bool`, `int`, `string` or a nullable class (`?User`), followed by a default value:

```php
public bool $isAdmin = false;
public int $maxItems = 10;
public string $role = 'user';
public ?User $user = null;
```

Such a property is flagged when the line directly above it does not contain `#[Locked]` and one of these holds:

- The name contains `admin`, `role`, `permission`, `auth`, `max`, `min`, `limit`, `redirect`, `available`, `allowed`, `cart`, `user`, `client`, `model`, `locale`, `config` or `setting`, in any case.
- The type is a nullable class (`?User`, `?Cart`).
- The type is `bool`, unless the name is `checked`, `selected`, `enabled` or `visible`.

## The output

With findings, the output looks like this. The file, line, property and type differ per finding; every other character is literal:

```
🔍 Scanning Livewire components for security issues...

⚠️  Potential vulnerabilities found:

[CRITICAL]
  📍 app/Livewire/Checkout.php:10
     Property: $isAdmin (bool)
     💡 Add #[Locked] attribute above this property

[HIGH]
  📍 app/Livewire/Checkout.php:12
     Property: $maxQuantity (int)
     💡 Add #[Locked] attribute above this property

Total: 2 vulnerable properties found

📖 See https://livewire.laravel.com/docs/locked for more information about #[Locked]
```

Without findings:

```
🔍 Scanning Livewire components for security issues...

✅ No security issues found!
```

`✅ No security issues found!` means no line matched the rules above. It also appears when `app/Livewire` does not exist or when no file in it was recognised. It is not a statement that the components are safe.

| Severity | Name contains |
| --- | --- |
| `[CRITICAL]` | `admin`, `role`, `permission` or `auth` |
| `[HIGH]` | `max`, `limit`, `user`, `client` or `cart` |
| `[MEDIUM]` | everything else that was flagged |

### Exit code

The exit code is `1` when at least one property was flagged and `0` otherwise, so the command can run in CI:

```yaml
# .github/workflows/tests.yml

- run: php artisan livewire-injection-stopper:audit
```

## Fixing a finding

Add `#[Locked]` on the line directly above the property and import the attribute. Livewire then throws `CannotUpdateLockedPropertyException` when a request tries to change the property, and this package answers that with the block response.

```php
<?php
// app/Livewire/Checkout.php

namespace App\Livewire;

use Livewire\Attributes\Locked;
use Livewire\Component;

class Checkout extends Component
{
    #[Locked]
    public bool $isAdmin = false;

    #[Locked]
    public int $maxQuantity = 10;
}
```

A locked property can still be changed by your own PHP code; only updates from the browser are refused.

If a flagged property really must be editable from the browser (`public bool $open = false` for a modal is a common one), validate its value in the action that uses it, and leave it. The audit keeps listing it: there is no ignore list.

## Limits

The audit is a text scan of single lines, not static analysis. It does not see:

- A property without a type, without a default value (`public int $userId;`), or spread over several lines.
- A property with another type, such as `float`, `array`, `?string`, `?int`, a non-nullable class (`User`) or a namespaced class (`?\App\Models\User`).
- A property that is not indented, or a line with anything before `public`, such as `#[Locked] public bool $x = false;` on one line.
- A component that extends your own base class (`extends BaseComponent`): the file must contain the text `extends Component`.
- Components outside `app/Livewire`, for example under `resources/views`, in a module or in a package.

It also flags by name and type, not by data flow. A `public float $price` is not flagged. `#[Locked]` is only recognised on the line directly above the property; an attribute two lines up, or combined as `#[Locked, Url]`, is not. Add `#[Locked]` to every property that a visitor must not change, whether the audit mentions it or not.
