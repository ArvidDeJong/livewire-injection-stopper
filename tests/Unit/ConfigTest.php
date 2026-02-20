<?php

declare(strict_types=1);

it('loads default configuration', function () {
    expect(config('livewire-injection-stopper.blocked_user_agents'))->toBeArray();
    expect(config('livewire-injection-stopper.blocked_ips'))->toBeArray();
    expect(config('livewire-injection-stopper.whitelist_routes'))->toBeArray();
    expect(config('livewire-injection-stopper.response_status'))->toBeInt();
    expect(config('livewire-injection-stopper.response_message'))->toBeString();
    expect(config('livewire-injection-stopper.log_blocked_requests'))->toBeBool();
});

it('has correct default blocked user agents', function () {
    $blockedAgents = config('livewire-injection-stopper.blocked_user_agents');

    expect($blockedAgents)
        ->toContain('python-requests')
        ->toContain('curl')
        ->toContain('wget')
        ->toContain('bot');
});

it('allows custom configuration', function () {
    config()->set('livewire-injection-stopper.blocked_user_agents', ['custom-bot']);

    expect(config('livewire-injection-stopper.blocked_user_agents'))->toBe(['custom-bot']);
});

it('has default response status 403', function () {
    expect(config('livewire-injection-stopper.response_status'))->toBe(403);
});

it('has logging configurable', function () {
    // Test that log_blocked_requests can be set to true or false
    config()->set('livewire-injection-stopper.log_blocked_requests', true);
    expect(config('livewire-injection-stopper.log_blocked_requests'))->toBeTrue();

    config()->set('livewire-injection-stopper.log_blocked_requests', false);
    expect(config('livewire-injection-stopper.log_blocked_requests'))->toBeFalse();
});
