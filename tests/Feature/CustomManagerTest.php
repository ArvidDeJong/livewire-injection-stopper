<?php

declare(strict_types=1);

use Darvis\LivewireInjectionStopper\Events\RequestBlocked;
use Darvis\LivewireInjectionStopper\Facades\LivewireInjectionStopper;
use Darvis\LivewireInjectionStopper\LivewireInjectionStopperManager;
use Darvis\LivewireInjectionStopper\Middleware\BlockInjectionAttempts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * The manager is the documented extension point: a host app binds its own subclass and every
 * caller picks it up, because the middleware, the facade and the exception handling all
 * resolve it from the container. Guards that the class stays extendable.
 */
class ManagerThatBlocksEveryBrowser extends LivewireInjectionStopperManager
{
    public function isBlockedUserAgent(?string $userAgent): bool
    {
        return str_contains((string) $userAgent, 'Mozilla');
    }
}

beforeEach(function () {
    app()->singleton(LivewireInjectionStopperManager::class, ManagerThatBlocksEveryBrowser::class);

    Route::middleware(BlockInjectionAttempts::class)->get('/test', fn () => response()->json(['success' => true]));
});

it('lets a bound subclass decide what the middleware blocks', function () {
    $this->get('/test', ['User-Agent' => 'Mozilla/5.0'])->assertForbidden();
});

it('keeps the checks it does not override', function () {
    $this->get('/test', ['User-Agent' => 'curl/8.0'])->assertForbidden();
    $this->get('/test', ['User-Agent' => 'Symfony'])->assertOk();
});

it('reaches the subclass through the facade too', function () {
    expect(app(LivewireInjectionStopperManager::class))->toBeInstanceOf(ManagerThatBlocksEveryBrowser::class);
    expect(LivewireInjectionStopper::check(Request::create('/test', 'GET', server: ['HTTP_USER_AGENT' => 'Mozilla/5.0'])))
        ->toBe(RequestBlocked::BLOCKED_USER_AGENT);
});
