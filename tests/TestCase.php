<?php

namespace Darvis\LivewireInjectionStopper\Tests;

use Darvis\LivewireInjectionStopper\LivewireInjectionStopperServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app)
    {
        return [
            LivewireInjectionStopperServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        config()->set('livewire-injection-stopper.blocked_user_agents', [
            'python-requests',
            'curl',
            'wget',
            'bot',
        ]);

        config()->set('livewire-injection-stopper.blocked_ips', [
            '192.168.1.100',
        ]);

        config()->set('livewire-injection-stopper.whitelist_routes', [
            'api/webhooks/*',
        ]);

        config()->set('livewire-injection-stopper.response_status', 403);
        config()->set('livewire-injection-stopper.log_blocked_requests', false);
    }
}
