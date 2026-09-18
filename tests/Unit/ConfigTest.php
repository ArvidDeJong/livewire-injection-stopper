<?php

declare(strict_types=1);
use Darvis\LivewireInjectionStopper\LivewireInjectionStopperServiceProvider;
use Illuminate\Support\ServiceProvider;

it('ships the defaults a host app gets without publishing the config', function () {
    expect(config('livewire-injection-stopper.blocked_user_agents'))
        ->toBeArray()
        ->toContain('python', 'curl/', 'wget', 'gptbot', 'claudebot')
        ->not->toContain('bot', 'spider', 'crawler');

    expect(config('livewire-injection-stopper.allowed_user_agents'))
        ->toContain('uptimerobot', 'pingdom', 'statuscake', 'sentryuptimebot');

    expect(config('livewire-injection-stopper.blocked_ips'))->toBe([]);
    expect(config('livewire-injection-stopper.whitelist_routes'))->toContain('api/webhooks/*');
    expect(config('livewire-injection-stopper.response_status'))->toBe(403);
    expect(config('livewire-injection-stopper.response_message'))->toBe('Access Denied');
    expect(config('livewire-injection-stopper.log_blocked_requests'))->toBeTrue();
    expect(config('livewire-injection-stopper.check_payload_injection'))->toBeTrue();
    expect(config('livewire-injection-stopper.block_all_array_injections'))->toBeTrue();
    expect(config('livewire-injection-stopper.scalar_properties'))->toContain('title', 'email', 'status');
    expect(config('livewire-injection-stopper.silence_locked_property_exceptions'))->toBeTrue();
});

it('publishes the config file under its own tag', function () {
    $paths = ServiceProvider::pathsToPublish(
        LivewireInjectionStopperServiceProvider::class,
        'livewire-injection-stopper-config'
    );

    expect($paths)->toHaveCount(1);
    expect(array_key_first($paths))->toEndWith('config/livewire-injection-stopper.php');
    expect(reset($paths))->toBe(config_path('livewire-injection-stopper.php'));
});
