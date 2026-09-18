<?php

declare(strict_types=1);

use Darvis\LivewireInjectionStopper\Events\RequestBlocked;
use Darvis\LivewireInjectionStopper\Middleware\BlockInjectionAttempts;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;

/**
 * Livewire 3 serves its update endpoint at /livewire/update, Livewire 4 at /livewire-<hash>/update.
 * Both name the route with a suffix of livewire.update.
 */
function livewireUpdateUri(): string
{
    foreach (Route::getRoutes()->getRoutes() as $route) {
        if (str_ends_with((string) $route->getName(), 'livewire.update')) {
            return '/'.ltrim($route->uri(), '/');
        }
    }

    throw new RuntimeException('Livewire did not register its update route.');
}

beforeEach(function () {
    Route::middleware(BlockInjectionAttempts::class)->get('/test', fn () => response()->json(['success' => true]));
    Route::middleware(BlockInjectionAttempts::class)->get('/api/webhooks/test', fn () => response()->json(['webhook' => true]));
});

it('is pushed onto the web group and available under its alias', function () {
    $router = app(Router::class);

    expect($router->getMiddlewareGroups()['web'])->toContain(BlockInjectionAttempts::class);
    expect($router->getMiddleware()['livewire-injection-stopper'])->toBe(BlockInjectionAttempts::class);
});

it('allows a browser', function () {
    $this->get('/test', ['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'])
        ->assertOk()
        ->assertJson(['success' => true]);
});

it('allows the test client, which sends the Symfony user agent', function () {
    $this->get('/test')->assertOk();
});

it('blocks scripted clients, case-insensitively', function (string $userAgent) {
    $this->get('/test', ['User-Agent' => $userAgent])->assertForbidden()->assertSee('Access Denied');
})->with(['python-requests/2.28.0', 'PYTHON-REQUESTS/2.28.0', 'curl/7.68.0', 'Wget/1.20.3']);

it('honours the allowed user agents in the middleware, not only in the facade', function () {
    config(['livewire-injection-stopper.blocked_user_agents' => ['bot']]);

    $this->get('/test', ['User-Agent' => 'UptimeRobot/2.0'])->assertOk();
    $this->get('/test', ['User-Agent' => 'EvilBot/1.0'])->assertForbidden();
});

it('blocks configured ip addresses', function () {
    config(['livewire-injection-stopper.blocked_ips' => ['192.168.1.100']]);

    $this->call('GET', '/test', [], [], [], ['REMOTE_ADDR' => '192.168.1.100', 'HTTP_USER_AGENT' => 'Mozilla/5.0'])
        ->assertForbidden();
});

it('skips every check on whitelisted routes', function () {
    $this->get('/api/webhooks/test', ['User-Agent' => 'python-requests/2.28.0'])
        ->assertOk()
        ->assertJson(['webhook' => true]);
});

it('uses the configured status and message', function () {
    config([
        'livewire-injection-stopper.response_status' => 404,
        'livewire-injection-stopper.response_message' => 'Nothing here',
    ]);

    $this->get('/test', ['User-Agent' => 'curl/8.0'])->assertNotFound()->assertSee('Nothing here');
});

it('dispatches RequestBlocked with the reason', function () {
    Event::fake([RequestBlocked::class]);

    $this->get('/test', ['User-Agent' => 'curl/8.0'])->assertForbidden();

    Event::assertDispatched(RequestBlocked::class, fn (RequestBlocked $event) => $event->reason === RequestBlocked::BLOCKED_USER_AGENT
        && $event->userAgent === 'curl/8.0');
});

it('blocks an array injection on the real Livewire update route through the web group', function () {
    Event::fake([RequestBlocked::class]);

    $payload = ['components' => [['snapshot' => '{}', 'updates' => ['title' => ['<script>']], 'calls' => []]]];

    $this->withSession(['_token' => 'csrf'])
        ->postJson(livewireUpdateUri(), $payload, ['User-Agent' => 'Mozilla/5.0', 'X-CSRF-TOKEN' => 'csrf'])
        ->assertForbidden();

    Event::assertDispatched(RequestBlocked::class, fn (RequestBlocked $event) => $event->reason === RequestBlocked::SUSPICIOUS_PAYLOAD);
});

it('does not inspect payloads outside the Livewire update route', function () {
    Route::middleware(BlockInjectionAttempts::class)->post('/contact', fn () => response()->json(['ok' => true]));

    $this->postJson('/contact', ['updates' => ['title' => ['x']]], ['User-Agent' => 'Mozilla/5.0'])->assertOk();
});
