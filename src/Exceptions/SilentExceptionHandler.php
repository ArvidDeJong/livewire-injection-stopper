<?php

declare(strict_types=1);

namespace Darvis\LivewireInjectionStopper\Exceptions;

use Darvis\LivewireInjectionStopper\LivewireInjectionStopperManager;
use Illuminate\Support\Facades\Log;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Throwable;
use TypeError;

/**
 * Decides which exceptions are bot noise: attempts to change a locked property,
 * or to assign an array to a typed property, through a manipulated Livewire payload.
 */
final class SilentExceptionHandler
{
    /**
     * Exception classes that are never reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    private const SILENT_EXCEPTIONS = [
        CannotUpdateLockedPropertyException::class,
    ];

    /**
     * Check if an exception should be silently handled.
     */
    public static function shouldSilence(Throwable $exception): bool
    {
        foreach (self::SILENT_EXCEPTIONS as $exceptionClass) {
            if ($exception instanceof $exceptionClass) {
                return true;
            }
        }

        return $exception instanceof TypeError && self::isLivewireArrayAssignmentTypeError($exception);
    }

    /**
     * Log the attempt locally. Kept for host apps that call it from a custom handler;
     * the package itself goes through LivewireInjectionStopperManager::reject().
     */
    public static function handle(Throwable $exception): void
    {
        if (! app(LivewireInjectionStopperManager::class)->logsBlockedRequests()) {
            return;
        }

        Log::warning('[LivewireInjectionStopper] Blocked Livewire property manipulation attempt', [
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
        ]);
    }

    /**
     * The exception classes that should not be reported to error tracking services.
     *
     * @return array<int, class-string<Throwable>>
     */
    public static function getDontReport(): array
    {
        return self::SILENT_EXCEPTIONS;
    }

    /**
     * A TypeError counts as an attack only when it was raised while Livewire assigned a payload value.
     */
    private static function isLivewireArrayAssignmentTypeError(TypeError $exception): bool
    {
        if (! str_contains($exception->getMessage(), 'Cannot assign array to property')) {
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
}
