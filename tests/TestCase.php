<?php

declare(strict_types=1);

namespace Darvis\LivewireInjectionStopper\Tests;

use Darvis\LivewireInjectionStopper\LivewireInjectionStopperServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @param  Application  $app
     * @return array<int, class-string<ServiceProvider>>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LivewireServiceProvider::class,
            LivewireInjectionStopperServiceProvider::class,
        ];
    }

    /**
     * An app key for Livewire snapshots and a silent log channel. The package config keeps its defaults,
     * so the tests exercise what a host app gets.
     *
     * @param  Application  $app
     */
    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('logging.default', 'null');
    }
}
