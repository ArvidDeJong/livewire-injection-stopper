---
title: "Quick start"
nav_order: 3
description: "One complete example: find an unlocked Livewire property with the audit command, fix it with #[Locked], and count blocked requests with the RequestBlocked event."
---

# Quick start

This page assumes the package is [installed](installation.md). The request filter already runs; nothing on this page is needed to turn it on. The example shows the two things you do yourself: lock properties and listen for blocked requests.

## 1. A component with a property a visitor must not change

A public property of a Livewire component can be changed from the browser: the visitor's browser sends the new value in the update request, and so can a script. Take this component:

```php
<?php
// app/Livewire/Checkout.php

namespace App\Livewire;

use Livewire\Component;

class Checkout extends Component
{
    public bool $isAdmin = false;

    public int $maxQuantity = 10;

    public string $coupon = '';

    public function render()
    {
        return view('livewire.checkout');
    }
}
```

`$coupon` is meant to be typed by the visitor. `$isAdmin` and `$maxQuantity` are not.

## 2. Run the audit

```bash
php artisan livewire-injection-stopper:audit
```

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

The command flags `$isAdmin` because its name contains `admin`, and `$maxQuantity` because its name contains `max`. It does not flag `$coupon`. The exit code is `1`.

## 3. Lock the properties

[`#[Locked]`](https://livewire.laravel.com/docs/locked) is a Livewire attribute: Livewire throws `CannotUpdateLockedPropertyException` when a request tries to change such a property.

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

    public string $coupon = '';

    public function render()
    {
        return view('livewire.checkout');
    }
}
```

Run the audit again. It now prints `✅ No security issues found!` and exits with `0`.

When a script replays an update request with `isAdmin` set to `true`, Livewire throws the exception. This package replaces the error response with the block response (`403 Access Denied` by default), writes a log line, dispatches `RequestBlocked` with the reason `locked_property`, and keeps the exception out of your error tracker.

## 4. Count what gets blocked

`RequestBlocked` is a Laravel [event](https://laravel.com/docs/events): the package dispatches it for every request it rejects, and your code can listen for it.

```php
<?php
// app/Providers/AppServiceProvider.php

namespace App\Providers;

use Darvis\LivewireInjectionStopper\Events\RequestBlocked;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(RequestBlocked::class, function (RequestBlocked $event): void {
            // $event->reason is blocked_ip, blocked_user_agent, suspicious_payload or locked_property.
            Cache::increment('blocked-requests:'.$event->reason.':'.now()->format('Y-m-d'));
        });
    }
}
```

Every blocked request now raises a counter per reason and per day in your cache. The event also carries `ip`, `userAgent`, `url` and `exception`; see [Bot blocking](bot-blocking.md#the-requestblocked-event).

## Next

- [How it works](how-it-works.md) lists the checks and their limits.
- [Payload injection](payload-injection.md#components-with-array-properties) explains the one default that can block a legitimate form: arrays sent to a top-level property.
