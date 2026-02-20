<?php

declare(strict_types=1);

use Darvis\LivewireInjectionStopper\Facades\LivewireInjectionStopper;
use Darvis\LivewireInjectionStopper\LivewireInjectionStopperManager;

it('can resolve manager from container', function () {
    $manager = app('livewire-injection-stopper');

    expect($manager)->toBeInstanceOf(LivewireInjectionStopperManager::class);
});

it('can use facade to check blocked user agent', function () {
    config(['livewire-injection-stopper.blocked_user_agents' => ['python']]);

    expect(LivewireInjectionStopper::isBlockedUserAgent('python-requests/2.0'))->toBeTrue();
    expect(LivewireInjectionStopper::isBlockedUserAgent('Mozilla/5.0'))->toBeFalse();
});

it('can use facade to check blocked ip', function () {
    config(['livewire-injection-stopper.blocked_ips' => ['192.168.1.100']]);

    expect(LivewireInjectionStopper::isBlockedIp('192.168.1.100'))->toBeTrue();
    expect(LivewireInjectionStopper::isBlockedIp('192.168.1.1'))->toBeFalse();
});

it('can use facade to check whitelisted routes', function () {
    config(['livewire-injection-stopper.whitelist_routes' => ['api/webhooks/*']]);

    expect(LivewireInjectionStopper::isWhitelisted('api/webhooks/test'))->toBeTrue();
    expect(LivewireInjectionStopper::isWhitelisted('api/users'))->toBeFalse();
});

it('respects allowed user agents whitelist', function () {
    config([
        'livewire-injection-stopper.blocked_user_agents' => ['bot'],
        'livewire-injection-stopper.allowed_user_agents' => ['uptimerobot'],
    ]);

    expect(LivewireInjectionStopper::isBlockedUserAgent('UptimeRobot/2.0'))->toBeFalse();
    expect(LivewireInjectionStopper::isBlockedUserAgent('EvilBot/1.0'))->toBeTrue();
});
