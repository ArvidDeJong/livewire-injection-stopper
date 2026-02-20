<?php

declare(strict_types=1);

namespace Darvis\LivewireInjectionStopper\Exceptions;

use Illuminate\Support\Facades\Log;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;

/**
 * Handler to silently catch Livewire locked property exceptions.
 * These are typically caused by bots trying to manipulate protected properties.
 */
final class SilentExceptionHandler
{
    /**
     * List of exception classes that should be silently handled.
     *
     * @var array<int, class-string<\Throwable>>
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

        if ($exception instanceof \TypeError && self::isLivewireArrayAssignmentTypeError($exception)) {
            return true;
        }

        return false;
    }

    /**
     * Check whether a TypeError was caused by a Livewire payload array assignment attack.
     */
    protected static function isLivewireArrayAssignmentTypeError(\TypeError $exception): bool
    {
        $message = $exception->getMessage();

        if (!str_contains($message, 'Cannot assign array to property')) {
            return false;
        }

        foreach ($exception->getTrace() as $frame) {
            $class = $frame['class'] ?? null;
            $file = $frame['file'] ?? null;

            if (is_string($class) && str_starts_with($class, 'Livewire\\')) {
                return true;
            }

            if (is_string($file) && str_contains(str_replace('\\', '/', $file), '/vendor/livewire/livewire/')) {
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
            Log::warning('[LivewireInjectionStopper] Blocked Livewire property manipulation attempt', [
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Get the list of exception classes that should not be reported to error tracking services.
     *
     * @return array<int, class-string<\Throwable>>
     */
    public static function getDontReport(): array
    {
        return self::$silentExceptions;
    }
}
