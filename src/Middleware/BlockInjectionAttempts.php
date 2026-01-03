<?php

namespace Darvis\LivewireInjectionStopper\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to block spam robots and injection attempts based on User-Agent and IP address.
 */
class BlockInjectionAttempts
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $userAgent = $request->userAgent();
        $ip = $request->ip();
        $route = $request->path();

        if ($this->isWhitelisted($route)) {
            return $next($request);
        }

        if ($this->isBlockedIp($ip)) {
            return $this->blockRequest($request, 'Blocked IP: ' . $ip);
        }

        if ($this->isBlockedUserAgent($userAgent)) {
            return $this->blockRequest($request, 'Blocked User-Agent: ' . $userAgent);
        }

        return $next($request);
    }

    /**
     * Check if the route is whitelisted.
     *
     * @param  string  $route
     * @return bool
     */
    protected function isWhitelisted(string $route): bool
    {
        $whitelist = config('livewire-injection-stopper.whitelist_routes', []);

        foreach ($whitelist as $pattern) {
            if (fnmatch($pattern, $route)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the IP address is blocked.
     *
     * @param  string|null  $ip
     * @return bool
     */
    protected function isBlockedIp(?string $ip): bool
    {
        if (!$ip) {
            return false;
        }

        $blockedIps = config('livewire-injection-stopper.blocked_ips', []);

        return in_array($ip, $blockedIps);
    }

    /**
     * Check if the User-Agent is blocked.
     *
     * @param  string|null  $userAgent
     * @return bool
     */
    protected function isBlockedUserAgent(?string $userAgent): bool
    {
        if (!$userAgent) {
            return false;
        }

        $blockedAgents = config('livewire-injection-stopper.blocked_user_agents', []);
        $userAgentLower = strtolower($userAgent);

        foreach ($blockedAgents as $pattern) {
            if (str_contains($userAgentLower, strtolower($pattern))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Block the request and return a response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $reason
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function blockRequest(Request $request, string $reason): Response
    {
        if (config('livewire-injection-stopper.log_blocked_requests', true)) {
            Log::warning('[LivewireInjectionStopper] ' . $reason, [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'url' => $request->fullUrl(),
                'method' => $request->method(),
            ]);
        }

        $statusCode = config('livewire-injection-stopper.response_status', 403);
        $message = config('livewire-injection-stopper.response_message', 'Access Denied');

        return response($message, $statusCode);
    }
}
