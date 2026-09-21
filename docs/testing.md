---
title: "Testing"
nav_order: 9
description: "Test a Laravel app that has darvis/livewire-injection-stopper installed: a complete feature test for blocked requests, config in tests, bypassing the middleware."
---

# Testing

Nothing on this page calls an external service. The package has none.

## Do existing tests keep passing?

Two facts decide that:

- Laravel's HTTP test client sends the User-Agent `Symfony` when you set none. That matches nothing in the default blocked list, so `$this->get('/')` passes the filter.
- `Livewire::test()` runs its requests with all middleware disabled, so this package's middleware never sees them. `->set('tags', ['a', 'b'])` on a top-level array property works in a component test, although the same update is rejected in a browser under the default settings; see [Payload injection](payload-injection.md#components-with-array-properties).

A test that sends its own User-Agent containing a blocked pattern, for example `axios` or `okhttp`, gets the block response.

## A complete test for blocked requests

```php
<?php
// tests/Feature/BlockedRequestsTest.php

namespace Tests\Feature;

use Darvis\LivewireInjectionStopper\Events\RequestBlocked;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class BlockedRequestsTest extends TestCase
{
    public function test_a_browser_gets_the_page(): void
    {
        $this->get('/', ['User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)'])
            ->assertOk();
    }

    public function test_a_scripted_client_is_blocked(): void
    {
        Event::fake([RequestBlocked::class]);

        $this->get('/', ['User-Agent' => 'python-requests/2.31.0'])
            ->assertForbidden()
            ->assertSee('Access Denied');

        Event::assertDispatched(
            RequestBlocked::class,
            fn (RequestBlocked $event) => $event->reason === RequestBlocked::BLOCKED_USER_AGENT
                && $event->userAgent === 'python-requests/2.31.0'
        );
    }

    public function test_a_listed_ip_address_is_blocked(): void
    {
        config(['livewire-injection-stopper.blocked_ips' => ['203.0.113.42']]);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.42'])
            ->get('/')
            ->assertForbidden();
    }

    public function test_an_array_sent_to_a_livewire_property_is_blocked(): void
    {
        Event::fake([RequestBlocked::class]);

        // Livewire 3 serves /livewire/update, Livewire 4 /livewire-<hash>/update. Find the route by name.
        $uri = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($route) => str_ends_with((string) $route->getName(), 'livewire.update'))
            ->uri();

        $this->postJson('/'.$uri, ['components' => [['updates' => ['title' => ['x']]]]])
            ->assertForbidden();

        Event::assertDispatched(
            RequestBlocked::class,
            fn (RequestBlocked $event) => $event->reason === RequestBlocked::SUSPICIOUS_PAYLOAD
        );
    }
}
```

The test assumes that `/` is a route in `routes/web.php` that answers with 200. The first test proves a browser passes. The second sends a blocked User-Agent and checks the 403, the body and the event. The third sets a config value inside the test and fakes the client address. The fourth posts an array for the property `title` to Livewire's update route; the package rejects it before Livewire reads the body, so no real component is needed. Laravel does not verify CSRF tokens while it runs tests, so the POST needs no token.

`Event::fake([RequestBlocked::class])` replaces only that event, so your own listeners for it do not run in that test.

## Changing options in a test

Set a value inside the test that needs it:

```php
config(['livewire-injection-stopper.blocked_user_agents' => ['bot']]);
config(['livewire-injection-stopper.block_all_array_injections' => false]);
```

Every key is read on each request. The one exception is the reporting half of `silence_locked_property_exceptions`: its hooks are registered when the package boots. To test with reporting on, set the key to false before the application boots, for example in the environment setup of a dedicated test case.

## Bypassing the middleware

```php
<?php

use Darvis\LivewireInjectionStopper\Middleware\BlockInjectionAttempts;

$this->withoutMiddleware(BlockInjectionAttempts::class);
```

After this call, requests in the same test skip the package's middleware and nothing is blocked.

## Running the package's own tests

```bash
git clone https://github.com/ArvidDeJong/livewire-injection-stopper.git
cd livewire-injection-stopper
composer install

composer test      # Pest
composer lint      # Pint, check only; composer format fixes
composer analyse   # Larastan, level 8
```

The suite uses the package defaults, not test-only config. CI runs it on PHP 8.2 to 8.4 with Laravel 11, 12 and 13, on the lowest and the latest dependencies.
