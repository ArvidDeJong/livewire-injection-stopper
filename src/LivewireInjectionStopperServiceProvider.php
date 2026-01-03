<?php

namespace Darvis\LivewireInjectionStopper;

use Darvis\LivewireInjectionStopper\Console\Commands\AuditLivewireSecurity;
use Darvis\LivewireInjectionStopper\Middleware\BlockInjectionAttempts;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class LivewireInjectionStopperServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/config/livewire-injection-stopper.php',
            'livewire-injection-stopper'
        );
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/config/livewire-injection-stopper.php' => config_path('livewire-injection-stopper.php'),
            ], 'livewire-injection-stopper-config');

            $this->commands([
                AuditLivewireSecurity::class,
            ]);
        }

        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('livewire-injection-stopper', BlockInjectionAttempts::class);
        $router->pushMiddlewareToGroup('web', BlockInjectionAttempts::class);
    }
}
