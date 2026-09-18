<?php

declare(strict_types=1);

namespace Darvis\LivewireInjectionStopper\Middleware;

use Closure;
use Darvis\LivewireInjectionStopper\Events\RequestBlocked;
use Darvis\LivewireInjectionStopper\Exceptions\SilentExceptionHandler;
use Darvis\LivewireInjectionStopper\LivewireInjectionStopperManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as IlluminateResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks spam bots, blocked IPs and suspicious Livewire payloads before the request runs,
 * and answers bot-driven Livewire exceptions after it. Every check lives in the manager.
 *
 * Extend LivewireInjectionStopperManager rather than this middleware: the checks it used to
 * hold as protected methods now live there, so an override here is never reached.
 */
class BlockInjectionAttempts
{
    public function __construct(protected readonly LivewireInjectionStopperManager $manager) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $reason = $this->manager->check($request);

        if ($reason !== null) {
            return $this->manager->reject($request, $reason);
        }

        $response = $next($request);

        // Laravel renders a controller exception inside the route pipeline, before the response
        // reaches this middleware, and attaches it to the response. That rendered response is
        // replaced here, whoever rendered it: Livewire 4 gives its locked-property exception
        // an own render() that returns 419 and wins over every renderable() callback.
        $exception = $response instanceof IlluminateResponse || $response instanceof JsonResponse
            ? $response->exception
            : null;

        if ($exception !== null && $this->manager->silencesLockedPropertyExceptions() && SilentExceptionHandler::shouldSilence($exception)) {
            return $this->manager->reject($request, RequestBlocked::LOCKED_PROPERTY, $exception);
        }

        return $response;
    }
}
