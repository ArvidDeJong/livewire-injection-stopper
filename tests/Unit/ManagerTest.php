<?php

declare(strict_types=1);

use Darvis\LivewireInjectionStopper\Events\RequestBlocked;
use Darvis\LivewireInjectionStopper\Facades\LivewireInjectionStopper;
use Darvis\LivewireInjectionStopper\LivewireInjectionStopperManager;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

function livewireUpdate(array $payload, string $path = '/livewire/update'): Request
{
    return Request::create($path, 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], (string) json_encode($payload));
}

it('is a singleton, also behind the string alias and the facade', function () {
    $manager = app(LivewireInjectionStopperManager::class);

    expect($manager)->toBe(app('livewire-injection-stopper'));
    expect(LivewireInjectionStopper::getFacadeRoot())->toBe($manager);
});

it('blocks scripted clients and crawlers with the default patterns', function (string $userAgent) {
    expect(LivewireInjectionStopper::isBlockedUserAgent($userAgent))->toBeTrue();
})->with([
    'python-requests/2.31.0',
    'Python-urllib/3.11',
    'curl/8.4.0',
    'Wget/1.21',
    'Go-http-client/1.1',
    'axios/1.6.0',
    'Mozilla/5.0 (compatible; GPTBot/1.0; +https://openai.com/gptbot)',
    'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)',
]);

it('lets browsers and search engines through with the default patterns', function (?string $userAgent) {
    expect(LivewireInjectionStopper::isBlockedUserAgent($userAgent))->toBeFalse();
})->with([
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36',
    'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
    'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)',
    'Symfony',
    '',
    null,
]);

it('lets an allowed user agent through even when it matches a blocked pattern', function () {
    config(['livewire-injection-stopper.blocked_user_agents' => ['bot']]);

    expect(LivewireInjectionStopper::isBlockedUserAgent('UptimeRobot/2.0'))->toBeFalse();
    expect(LivewireInjectionStopper::isBlockedUserAgent('Pingdom.com_bot_version_1.4'))->toBeFalse();
    expect(LivewireInjectionStopper::isBlockedUserAgent('EvilBot/1.0'))->toBeTrue();
});

it('blocks only listed ip addresses', function () {
    config(['livewire-injection-stopper.blocked_ips' => ['203.0.113.42']]);

    expect(LivewireInjectionStopper::isBlockedIp('203.0.113.42'))->toBeTrue();
    expect(LivewireInjectionStopper::isBlockedIp('203.0.113.4'))->toBeFalse();
    expect(LivewireInjectionStopper::isBlockedIp(null))->toBeFalse();
});

it('matches whitelisted routes with fnmatch patterns', function () {
    expect(LivewireInjectionStopper::isWhitelisted('api/webhooks/stripe'))->toBeTrue();
    expect(LivewireInjectionStopper::isWhitelisted('api/mollie-webhook'))->toBeTrue();
    expect(LivewireInjectionStopper::isWhitelisted('api/users'))->toBeFalse();
});

it('recognises the Livewire update endpoint by path and by route name', function () {
    expect(LivewireInjectionStopper::isLivewireUpdateRequest(livewireUpdate([])))->toBeTrue();
    expect(LivewireInjectionStopper::isLivewireUpdateRequest(livewireUpdate([], '/contact')))->toBeFalse();

    $custom = livewireUpdate([], '/custom/endpoint');
    $custom->setRouteResolver(fn () => (new Route('POST', '/custom/endpoint', fn () => null))->name('livewire.update'));

    expect(LivewireInjectionStopper::isLivewireUpdateRequest($custom))->toBeTrue();
});

it('flags an array sent to a top-level property in every payload format Livewire uses', function (array $payload) {
    expect(LivewireInjectionStopper::hasSuspiciousPayload(livewireUpdate($payload)))->toBeTrue();
})->with([
    'component updates map' => [['components' => [['updates' => ['title' => ['<script>']]]]]],
    'top-level updates map' => [['updates' => ['is_admin' => [true]]]],
    'name/value list' => [['updates' => [['name' => 'email', 'value' => ['x']]]]],
    'payload list' => [['updates' => [['payload' => ['name' => 'status', 'value' => ['x']]]]]],
]);

it('leaves scalar values and nested array keys alone', function (array $payload) {
    expect(LivewireInjectionStopper::hasSuspiciousPayload(livewireUpdate($payload)))->toBeFalse();
})->with([
    'scalar value' => [['components' => [['updates' => ['title' => 'Hello']]]]],
    'nested key' => [['components' => [['updates' => ['form.tags' => ['a', 'b']]]]]],
    'no updates' => [['components' => [['snapshot' => '{}', 'calls' => []]]]],
    'not json' => [[]],
]);

it('only checks known scalar names and prefixes when block_all_array_injections is off', function () {
    config(['livewire-injection-stopper.block_all_array_injections' => false]);

    expect(LivewireInjectionStopper::hasSuspiciousPayload(livewireUpdate(['updates' => ['tags' => ['a']]])))->toBeFalse();
    expect(LivewireInjectionStopper::hasSuspiciousPayload(livewireUpdate(['updates' => ['title' => ['a']]])))->toBeTrue();
    expect(LivewireInjectionStopper::hasSuspiciousPayload(livewireUpdate(['updates' => ['is_admin' => ['a']]])))->toBeTrue();
    expect(LivewireInjectionStopper::hasSuspiciousPayload(livewireUpdate(['updates' => ['show_modal' => ['a']]])))->toBeTrue();
    expect(LivewireInjectionStopper::hasSuspiciousPayload(livewireUpdate(['updates' => ['visibleItems' => ['a']]])))->toBeTrue();
});

it('skips the payload check when it is disabled', function () {
    config(['livewire-injection-stopper.check_payload_injection' => false]);

    expect(LivewireInjectionStopper::hasSuspiciousPayload(livewireUpdate(['updates' => ['title' => ['a']]])))->toBeFalse();
});

it('ignores an empty or non-json body', function () {
    $request = Request::create('/livewire/update', 'POST', [], [], [], [], 'not json');

    expect(LivewireInjectionStopper::hasSuspiciousPayload($request))->toBeFalse();
    expect(LivewireInjectionStopper::hasSuspiciousPayload(Request::create('/livewire/update', 'POST')))->toBeFalse();
});

it('runs the checks in order and reports the first reason', function () {
    config(['livewire-injection-stopper.blocked_ips' => ['203.0.113.42']]);

    $whitelisted = Request::create('/api/webhooks/x', 'POST', [], [], [], ['HTTP_USER_AGENT' => 'curl/8.0', 'REMOTE_ADDR' => '203.0.113.42']);
    $blockedIp = Request::create('/contact', 'GET', [], [], [], ['HTTP_USER_AGENT' => 'curl/8.0', 'REMOTE_ADDR' => '203.0.113.42']);
    $blockedAgent = Request::create('/contact', 'GET', [], [], [], ['HTTP_USER_AGENT' => 'curl/8.0']);
    $payload = livewireUpdate(['updates' => ['title' => ['x']]]);
    $payloadElsewhere = livewireUpdate(['updates' => ['title' => ['x']]], '/contact');
    $normal = Request::create('/contact', 'GET', [], [], [], ['HTTP_USER_AGENT' => 'Mozilla/5.0']);

    expect(LivewireInjectionStopper::check($whitelisted))->toBeNull();
    expect(LivewireInjectionStopper::check($blockedIp))->toBe(RequestBlocked::BLOCKED_IP);
    expect(LivewireInjectionStopper::check($blockedAgent))->toBe(RequestBlocked::BLOCKED_USER_AGENT);
    expect(LivewireInjectionStopper::check($payload))->toBe(RequestBlocked::SUSPICIOUS_PAYLOAD);
    expect(LivewireInjectionStopper::check($payloadElsewhere))->toBeNull();
    expect(LivewireInjectionStopper::check($normal))->toBeNull();
});

it('rejects with the configured response, a log line and the RequestBlocked event', function () {
    config([
        'livewire-injection-stopper.response_status' => 404,
        'livewire-injection-stopper.response_message' => 'Not Found',
    ]);
    Event::fake([RequestBlocked::class]);
    Log::spy();

    $request = Request::create('/contact', 'GET', [], [], [], ['HTTP_USER_AGENT' => 'curl/8.0', 'REMOTE_ADDR' => '198.51.100.7']);
    $response = LivewireInjectionStopper::reject($request, RequestBlocked::BLOCKED_USER_AGENT);

    expect($response->getStatusCode())->toBe(404);
    expect($response->getContent())->toBe('Not Found');

    Event::assertDispatched(RequestBlocked::class, fn (RequestBlocked $event) => $event->reason === RequestBlocked::BLOCKED_USER_AGENT
        && $event->ip === '198.51.100.7'
        && $event->userAgent === 'curl/8.0'
        && $event->url === 'http://localhost/contact'
        && $event->exception === null);

    Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context) => $message === '[LivewireInjectionStopper] Blocked User-Agent: curl/8.0'
        && $context['reason'] === RequestBlocked::BLOCKED_USER_AGENT
        && $context['ip'] === '198.51.100.7');
});

it('does not log when logging is disabled but still dispatches the event', function () {
    config(['livewire-injection-stopper.log_blocked_requests' => false]);
    Event::fake([RequestBlocked::class]);
    Log::spy();

    LivewireInjectionStopper::reject(Request::create('/contact'), RequestBlocked::BLOCKED_IP);

    Log::shouldNotHaveReceived('warning');
    Event::assertDispatched(RequestBlocked::class);
});

it('adds the exception to the log context and the event', function () {
    Event::fake([RequestBlocked::class]);
    Log::spy();
    $exception = new RuntimeException('Cannot update locked property: [isAdmin]');

    LivewireInjectionStopper::reject(Request::create('/livewire/update', 'POST'), RequestBlocked::LOCKED_PROPERTY, $exception);

    Event::assertDispatched(RequestBlocked::class, fn (RequestBlocked $event) => $event->exception === $exception);
    Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context) => $message === '[LivewireInjectionStopper] Blocked Livewire property manipulation attempt'
        && $context['exception'] === RuntimeException::class
        && $context['message'] === 'Cannot update locked property: [isAdmin]');
});
