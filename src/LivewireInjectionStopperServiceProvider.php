<?php

declare(strict_types=1);

namespace Darvis\LivewireInjectionStopper;

use Darvis\LivewireInjectionStopper\Console\Commands\AuditLivewireSecurity;
use Darvis\LivewireInjectionStopper\Exceptions\SilentExceptionHandler;
use Darvis\LivewireInjectionStopper\Middleware\BlockInjectionAttempts;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

final class LivewireInjectionStopperServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/config/livewire-injection-stopper.php',
            'livewire-injection-stopper'
        );

        $this->app->singleton('livewire-injection-stopper', function ($app) {
            return new LivewireInjectionStopperManager;
        });

        $this->app->alias('livewire-injection-stopper', LivewireInjectionStopperManager::class);
    }

    public function boot(): void
    {
        $this->registerPublishing();
        $this->registerCommands();
        $this->registerMiddleware();
        $this->registerExceptionHandling();
    }

    /**
     * Register the package's publishable resources.
     */
    protected function registerPublishing(): void
    {
        if (!$this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/config/livewire-injection-stopper.php' => config_path('livewire-injection-stopper.php'),
        ], 'livewire-injection-stopper-config');
    }

    /**
     * Register the package's artisan commands.
     */
    protected function registerCommands(): void
    {
        if (!$this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            AuditLivewireSecurity::class,
        ]);
    }

    /**
     * Register the middleware.
     */
    protected function registerMiddleware(): void
    {
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('livewire-injection-stopper', BlockInjectionAttempts::class);
        $router->pushMiddlewareToGroup('web', BlockInjectionAttempts::class);
    }

    /**
     * Register exception handling to silence locked property exceptions.
     * This prevents Sentry and other error tracking services from logging
     * bot attempts to manipulate locked Livewire properties.
     */
    protected function registerExceptionHandling(): void
    {
        if (!config('livewire-injection-stopper.silence_locked_property_exceptions', true)) {
            return;
        }

        $this->app->resolving(ExceptionHandler::class, function ($handler) {
            if (method_exists($handler, 'dontReport')) {
                $handler->dontReport(SilentExceptionHandler::getDontReport());
            }

            if (method_exists($handler, 'reportable')) {
                $reportable = $handler->reportable(function (\Throwable $e) {
                    if (!SilentExceptionHandler::shouldSilence($e)) {
                        return null;
                    }

                    return false;
                });

                if (is_object($reportable) && method_exists($reportable, 'stop')) {
                    $reportable->stop();
                }
            }

            if (method_exists($handler, 'renderable')) {
                $handler->renderable(function (\Throwable $e, $request) {
                    if (!SilentExceptionHandler::shouldSilence($e)) {
                        return null;
                    }

                    SilentExceptionHandler::handle($e);

                    $statusCode = config('livewire-injection-stopper.response_status', 403);
                    $message = config('livewire-injection-stopper.response_message', 'Access Denied');

                    return response($message, $statusCode);
                });
            }
        });
    }
}
