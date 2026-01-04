<?php

namespace Darvis\LivewireInjectionStopper;

use Darvis\LivewireInjectionStopper\Console\Commands\AuditLivewireSecurity;
use Darvis\LivewireInjectionStopper\Exceptions\SilentExceptionHandler;
use Darvis\LivewireInjectionStopper\Middleware\BlockInjectionAttempts;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;

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

        $this->registerExceptionHandling();
    }

    /**
     * Register exception handling to silence locked property exceptions.
     * This prevents Sentry and other error tracking services from logging
     * bot attempts to manipulate locked Livewire properties.
     */
    protected function registerExceptionHandling(): void
    {
        if (! config('livewire-injection-stopper.silence_locked_property_exceptions', true)) {
            return;
        }

        $this->app->resolving(ExceptionHandler::class, function ($handler) {
            // Register renderable callback to return 403 response
            if (method_exists($handler, 'renderable')) {
                $handler->renderable(function (CannotUpdateLockedPropertyException $e, $request) {
                    SilentExceptionHandler::handle($e);

                    $statusCode = config('livewire-injection-stopper.response_status', 403);
                    $message = config('livewire-injection-stopper.response_message', 'Access Denied');

                    return response($message, $statusCode);
                });
            }
        });
    }
}
