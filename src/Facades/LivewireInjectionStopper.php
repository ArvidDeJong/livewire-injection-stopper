<?php

declare(strict_types=1);

namespace Darvis\LivewireInjectionStopper\Facades;

use Darvis\LivewireInjectionStopper\LivewireInjectionStopperManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * @method static string|null check(Request $request)
 * @method static Response reject(Request $request, string $reason, ?Throwable $exception = null)
 * @method static bool isBlockedUserAgent(?string $userAgent)
 * @method static bool isBlockedIp(?string $ip)
 * @method static bool isWhitelisted(string $route)
 * @method static bool isLivewireUpdateRequest(Request $request)
 * @method static bool hasSuspiciousPayload(Request $request)
 *
 * @see LivewireInjectionStopperManager
 */
final class LivewireInjectionStopper extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return LivewireInjectionStopperManager::class;
    }
}
