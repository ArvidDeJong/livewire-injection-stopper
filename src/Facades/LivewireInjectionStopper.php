<?php

declare(strict_types=1);

namespace Darvis\LivewireInjectionStopper\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static bool isBlockedUserAgent(?string $userAgent)
 * @method static bool isBlockedIp(?string $ip)
 * @method static bool isWhitelisted(string $route)
 * @method static bool hasSuspiciousPayload(\Illuminate\Http\Request $request)
 *
 * @see \Darvis\LivewireInjectionStopper\LivewireInjectionStopperManager
 */
final class LivewireInjectionStopper extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'livewire-injection-stopper';
    }
}
