<?php

namespace Darvis\LivewireInjectionStopper\Exceptions;

use Illuminate\Support\Facades\Log;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;

/**
 * Handler to silently catch Livewire locked property exceptions.
 * These are typically caused by bots trying to manipulate protected properties.
 */
class SilentExceptionHandler
{
    /**
     * List of exception classes that should be silently handled.
     */
    protected static array $silentExceptions = [
        CannotUpdateLockedPropertyException::class,
    ];

    /**
     * Check if an exception should be silently handled.
     */
    public static function shouldSilence(\Throwable $exception): bool
    {
        foreach (self::$silentExceptions as $exceptionClass) {
            if ($exception instanceof $exceptionClass) {
                return true;
            }
        }

        return false;
    }

    /**
     * Handle the exception silently (log it locally but don't report to Sentry).
     */
    public static function handle(\Throwable $exception): void
    {
        if (config('livewire-injection-stopper.log_blocked_requests', true)) {
            Log::warning('[LivewireInjectionStopper] Blocked locked property manipulation attempt', [
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Get the list of exception classes that should not be reported to error tracking services.
     */
    public static function getDontReport(): array
    {
        return self::$silentExceptions;
    }
}
