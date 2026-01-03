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

        if ($this->isLivewireUpdateRoute($route) && $this->hasSuspiciousPayload($request)) {
            return $this->blockRequest($request, 'Suspicious Livewire payload detected');
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
     * Check if this is a Livewire update route.
     *
     * @param  string  $route
     * @return bool
     */
    protected function isLivewireUpdateRoute(string $route): bool
    {
        return str_contains($route, 'livewire/update');
    }

    /**
     * Check if the request contains suspicious Livewire payload.
     * Detects attempts to inject arrays into scalar properties.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    protected function hasSuspiciousPayload(Request $request): bool
    {
        if (!config('livewire-injection-stopper.check_payload_injection', true)) {
            return false;
        }

        $content = $request->getContent();
        if (empty($content)) {
            return false;
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            return false;
        }

        // Check for updates in the Livewire payload
        $components = $data['components'] ?? [];
        foreach ($components as $component) {
            $updates = $component['updates'] ?? [];
            foreach ($updates as $key => $value) {
                // Flag if an array is being sent to a property that looks like a boolean/scalar
                // Common boolean property patterns
                if (is_array($value) && $this->looksLikeScalarProperty($key)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if a property name suggests it should be a scalar value.
     *
     * @param  string  $propertyName
     * @return bool
     */
    protected function looksLikeScalarProperty(string $propertyName): bool
    {
        $scalarPrefixes = ['is_', 'has_', 'show_', 'can_', 'should_', 'enable', 'disable', 'active', 'visible', 'hidden'];
        $propertyLower = strtolower($propertyName);

        foreach ($scalarPrefixes as $prefix) {
            if (str_starts_with($propertyLower, $prefix)) {
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
