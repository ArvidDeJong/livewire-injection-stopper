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
            return $this->blockRequest($request, 'Blocked IP: '.$ip);
        }

        if ($this->isBlockedUserAgent($userAgent)) {
            return $this->blockRequest($request, 'Blocked User-Agent: '.$userAgent);
        }

        if ($this->isLivewireUpdateRoute($route) && $this->hasSuspiciousPayload($request)) {
            return $this->blockRequest($request, 'Suspicious Livewire payload detected');
        }

        return $next($request);
    }

    /**
     * Check if the route is whitelisted.
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
     */
    protected function isBlockedIp(?string $ip): bool
    {
        if (! $ip) {
            return false;
        }

        $blockedIps = config('livewire-injection-stopper.blocked_ips', []);

        return in_array($ip, $blockedIps);
    }

    /**
     * Check if the User-Agent is blocked.
     */
    protected function isBlockedUserAgent(?string $userAgent): bool
    {
        if (! $userAgent) {
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
     */
    protected function isLivewireUpdateRoute(string $route): bool
    {
        return str_contains($route, 'livewire/update');
    }

    /**
     * Check if the request contains suspicious Livewire payload.
     * Detects attempts to inject arrays into scalar properties.
     */
    protected function hasSuspiciousPayload(Request $request): bool
    {
        if (! config('livewire-injection-stopper.check_payload_injection', true)) {
            return false;
        }

        $content = $request->getContent();
        if (empty($content)) {
            return false;
        }

        $data = json_decode($content, true);
        if (! is_array($data)) {
            return false;
        }

        // Check for updates in the Livewire payload
        $components = $data['components'] ?? [];
        foreach ($components as $component) {
            $updates = $component['updates'] ?? [];
            foreach ($updates as $key => $value) {
                // Block ALL array injections to simple property names (no dots = not nested)
                // This catches type confusion attacks like injecting arrays into string/bool properties
                if (is_array($value) && $this->looksLikeScalarProperty($key)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if a property name suggests it should be a scalar value.
     * Now also blocks arrays sent to simple (non-nested) properties as a safety measure.
     */
    protected function looksLikeScalarProperty(string $propertyName): bool
    {
        // Known scalar property patterns (prefixes)
        $scalarPrefixes = ['is_', 'has_', 'show_', 'can_', 'should_', 'enable', 'disable', 'active', 'visible', 'hidden'];
        $propertyLower = strtolower($propertyName);

        foreach ($scalarPrefixes as $prefix) {
            if (str_starts_with($propertyLower, $prefix)) {
                return true;
            }
        }

        // Known scalar property names (exact match)
        $scalarProperties = config('livewire-injection-stopper.scalar_properties', [
            'style', 'class', 'id', 'name', 'title', 'label', 'value', 'text', 'content',
            'description', 'placeholder', 'type', 'status', 'state', 'mode', 'color',
            'size', 'width', 'height', 'url', 'href', 'src', 'alt', 'icon', 'image',
            'email', 'phone', 'address', 'message', 'subject', 'body', 'slug', 'path',
        ]);

        if (in_array($propertyLower, $scalarProperties)) {
            return true;
        }

        // Block arrays to simple property names (no dots = top-level property)
        // This is aggressive but catches most injection attempts
        if (config('livewire-injection-stopper.block_all_array_injections', true)) {
            if (! str_contains($propertyName, '.')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Block the request and return a response.
     */
    protected function blockRequest(Request $request, string $reason): Response
    {
        if (config('livewire-injection-stopper.log_blocked_requests', true)) {
            Log::warning('[LivewireInjectionStopper] '.$reason, [
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
