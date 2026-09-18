<?php

declare(strict_types=1);

namespace Darvis\LivewireInjectionStopper;

use Darvis\LivewireInjectionStopper\Console\Commands\AuditLivewireSecurity;
use Darvis\LivewireInjectionStopper\Exceptions\SilentExceptionHandler;
use Darvis\LivewireInjectionStopper\Middleware\BlockInjectionAttempts;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Throwable;

final class LivewireInjectionStopperServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/livewire-injection-stopper.php', 'livewire-injection-stopper');

        $this->app->singleton(LivewireInjectionStopperManager::class);
        $this->app->alias(LivewireInjectionStopperManager::class, 'livewire-injection-stopper');
    }

    public function boot(): void
    {
        $this->registerPublishing();
        $this->registerCommands();
        $this->registerMiddleware();
        $this->registerExceptionHandling();
    }

    private function registerPublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/livewire-injection-stopper.php' => config_path('livewire-injection-stopper.php'),
        ], 'livewire-injection-stopper-config');
    }

    private function registerCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            AuditLivewireSecurity::class,
        ]);
    }

    /**
     * The middleware runs on every web request; the alias is for other groups or single routes.
     */
    private function registerMiddleware(): void
    {
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('livewire-injection-stopper', BlockInjectionAttempts::class);
        $router->pushMiddlewareToGroup('web', BlockInjectionAttempts::class);
    }

    /**
     * Keep bot-driven Livewire exceptions out of error tracking. The block response itself
     * is produced by the middleware, which replaces the rendered error response; a
     * renderable() callback would lose against the exception's own render() on Livewire 4.
     *
     * dontReport() is order-independent: Handler::report() consults it before any callback,
     * including one Sentry registered earlier. The reportable() callback covers the TypeError
     * case, which has no exception class of its own; it runs before callbacks the app registers
     * in withExceptions(), because resolving() fires before afterResolving(). Both hooks are
     * looked up with method_exists() on purpose: in tests and under Octane, Collision wraps the
     * app handler in a decorator that forwards reportable() but has no dontReport().
     */
    private function registerExceptionHandling(): void
    {
        if (! $this->app->make(LivewireInjectionStopperManager::class)->silencesLockedPropertyExceptions()) {
            return;
        }

        $configure = function (object $handler): void {
            if (method_exists($handler, 'dontReport')) {
                $handler->dontReport(SilentExceptionHandler::getDontReport());
            }

            if (method_exists($handler, 'reportable')) {
                // Returning false stops the reporting of this one exception. Never add ->stop():
                // on a callback that handles every Throwable it stops the reporting of every
                // exception in the application, which is what 1.2.3 shipped.
                $handler->reportable(function (Throwable $e): ?bool {
                    return SilentExceptionHandler::shouldSilence($e) ? false : null;
                });
            }
        };

        $this->app->resolving(ExceptionHandler::class, $configure);

        if ($this->app->resolved(ExceptionHandler::class)) {
            $configure($this->app->make(ExceptionHandler::class));
        }
    }
}
