---
title: Testing
nav_order: 7
description: How the package behaves in your feature tests, how to assert that a request was blocked, how to bypass the middleware, and how to run the package's own test suite.
---

# Testing

## Your feature tests are not affected

Laravel's HTTP test client sends the User-Agent `Symfony`, which matches nothing in the blocked list, and `Livewire::test()` never goes through the HTTP layer. So installing the package does not change a passing test suite.

## Asserting that a request is blocked

Pass a User-Agent header and fake the event:

```php
use Darvis\LivewireInjectionStopper\Events\RequestBlocked;
use Illuminate\Support\Facades\Event;

it('blocks scripted clients', function () {
    Event::fake([RequestBlocked::class]);

    $this->get('/contact', ['User-Agent' => 'python-requests/2.31.0'])->assertForbidden();

    Event::assertDispatched(RequestBlocked::class, fn (RequestBlocked $event) => $event->reason === RequestBlocked::BLOCKED_USER_AGENT);
});
```

For an IP, use `$this->call()` with `REMOTE_ADDR` in the server array.

For the payload inspection, post JSON to Livewire's update route with an array under `components.0.updates`. The route is `/livewire/update` in Livewire 3 and `/livewire-<hash>/update` in Livewire 4; its name ends in `livewire.update` in both. The CSRF middleware runs first, so put a token in the session and send it along:

```php
$uri = collect(Route::getRoutes()->getRoutes())
    ->first(fn ($route) => str_ends_with((string) $route->getName(), 'livewire.update'))
    ->uri();

$this->withSession(['_token' => 'csrf'])
    ->postJson('/'.$uri, ['components' => [['updates' => ['title' => ['x']]]]], ['X-CSRF-TOKEN' => 'csrf'])
    ->assertForbidden();
```

## Changing options in a test

Every option except `silence_locked_property_exceptions` is read on each request:

```php
config(['livewire-injection-stopper.blocked_user_agents' => ['bot']]);
config(['livewire-injection-stopper.block_all_array_injections' => false]);
```

The reporting hooks behind `silence_locked_property_exceptions` are registered when the package boots. To test with it off, set it in the environment setup of a dedicated test case, before the application boots.

## Bypassing the middleware

```php
$this->withoutMiddleware(\Darvis\LivewireInjectionStopper\Middleware\BlockInjectionAttempts::class);
```

Or empty the lists in the test environment: `blocked_user_agents`, `blocked_ips` and `check_payload_injection` to false.

## Running the package's own tests

```bash
git clone https://github.com/ArvidDeJong/livewire-injection-stopper.git
cd livewire-injection-stopper
composer install

composer test      # Pest
composer lint      # Pint, check only; composer format fixes
composer analyse   # Larastan, level 8
```

The suite uses the package defaults, not test-only config, so it exercises what a host app gets. CI runs it on PHP 8.2 to 8.4 with Laravel 11, 12 and 13, on the lowest and the latest dependencies.
