<?php

declare(strict_types=1);

namespace Darvis\LivewireInjectionStopper;

use Darvis\LivewireInjectionStopper\Events\RequestBlocked;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * The one place that reads the package config and decides whether a request is blocked.
 * The middleware and the exception handling both delegate to it.
 *
 * This class is the extension point of the package. An application that needs a check
 * of its own extends it and binds the subclass in a service provider:
 * $this->app->singleton(LivewireInjectionStopperManager::class, MyManager::class);
 */
class LivewireInjectionStopperManager
{
    /**
     * Property name prefixes that indicate a scalar (usually boolean) value.
     *
     * @var array<int, string>
     */
    private const SCALAR_PREFIXES = ['is_', 'has_', 'show_', 'can_', 'should_', 'enable', 'disable', 'active', 'visible', 'hidden'];

    /**
     * Run every check in order. Returns the reason the request must be blocked, or null when it may pass.
     */
    public function check(Request $request): ?string
    {
        if ($this->isWhitelisted($request->path())) {
            return null;
        }

        if ($this->isBlockedIp($request->ip())) {
            return RequestBlocked::BLOCKED_IP;
        }

        if ($this->isBlockedUserAgent($request->userAgent())) {
            return RequestBlocked::BLOCKED_USER_AGENT;
        }

        if ($this->isLivewireUpdateRequest($request) && $this->hasSuspiciousPayload($request)) {
            return RequestBlocked::SUSPICIOUS_PAYLOAD;
        }

        return null;
    }

    /**
     * The single rejection path: log, dispatch RequestBlocked, then build the response.
     */
    public function reject(Request $request, string $reason, ?Throwable $exception = null): Response
    {
        if ($this->logsBlockedRequests()) {
            $context = [
                'reason' => $reason,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'url' => $request->fullUrl(),
                'method' => $request->method(),
            ];

            if ($exception !== null) {
                $context['exception'] = $exception::class;
                $context['message'] = $exception->getMessage();
            }

            Log::warning('[LivewireInjectionStopper] '.$this->describe($reason, $request), $context);
        }

        event(new RequestBlocked($reason, $request->ip(), $request->userAgent(), $request->fullUrl(), $exception));

        return response($this->responseMessage(), $this->responseStatus());
    }

    /**
     * Check if the User-Agent matches a blocked pattern and no allowed pattern.
     */
    public function isBlockedUserAgent(?string $userAgent): bool
    {
        if ($userAgent === null || $userAgent === '') {
            return false;
        }

        $userAgentLower = strtolower($userAgent);

        foreach ($this->allowedUserAgents() as $pattern) {
            if (str_contains($userAgentLower, strtolower($pattern))) {
                return false;
            }
        }

        foreach ($this->blockedUserAgents() as $pattern) {
            if (str_contains($userAgentLower, strtolower($pattern))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the IP address is blocked.
     */
    public function isBlockedIp(?string $ip): bool
    {
        if ($ip === null || $ip === '') {
            return false;
        }

        return in_array($ip, $this->blockedIps(), true);
    }

    /**
     * Check if the route path (without a leading slash) matches a whitelist pattern.
     */
    public function isWhitelisted(string $route): bool
    {
        foreach ($this->whitelistedRoutes() as $pattern) {
            if (fnmatch($pattern, $route)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the request goes to Livewire's update endpoint, by route name or by path.
     */
    public function isLivewireUpdateRequest(Request $request): bool
    {
        $route = $request->route();

        if ($route instanceof Route) {
            $name = $route->getName();

            if (is_string($name) && str_ends_with($name, 'livewire.update')) {
                return true;
            }
        }

        return str_contains($request->path(), 'livewire/update');
    }

    /**
     * Check if the request body sends an array to a Livewire property that should be scalar.
     */
    public function hasSuspiciousPayload(Request $request): bool
    {
        if (! $this->checksPayloadInjection()) {
            return false;
        }

        $content = $request->getContent();

        if ($content === '') {
            return false;
        }

        $data = json_decode($content, true);

        if (! is_array($data)) {
            return false;
        }

        foreach ($this->extractPropertyUpdates($data) as $update) {
            if (is_array($update['value']) && $this->looksLikeScalarProperty($update['property'])) {
                return true;
            }
        }

        return false;
    }

    public function logsBlockedRequests(): bool
    {
        return (bool) config('livewire-injection-stopper.log_blocked_requests', true);
    }

    public function responseStatus(): int
    {
        return (int) config('livewire-injection-stopper.response_status', 403);
    }

    public function responseMessage(): string
    {
        return (string) config('livewire-injection-stopper.response_message', 'Access Denied');
    }

    public function silencesLockedPropertyExceptions(): bool
    {
        return (bool) config('livewire-injection-stopper.silence_locked_property_exceptions', true);
    }

    public function checksPayloadInjection(): bool
    {
        return (bool) config('livewire-injection-stopper.check_payload_injection', true);
    }

    public function blocksAllArrayInjections(): bool
    {
        return (bool) config('livewire-injection-stopper.block_all_array_injections', true);
    }

    /**
     * @return array<int, string>
     */
    public function blockedUserAgents(): array
    {
        return $this->stringList('blocked_user_agents');
    }

    /**
     * @return array<int, string>
     */
    public function allowedUserAgents(): array
    {
        return $this->stringList('allowed_user_agents');
    }

    /**
     * @return array<int, string>
     */
    public function blockedIps(): array
    {
        return $this->stringList('blocked_ips');
    }

    /**
     * @return array<int, string>
     */
    public function whitelistedRoutes(): array
    {
        return $this->stringList('whitelist_routes');
    }

    /**
     * @return array<int, string>
     */
    public function scalarProperties(): array
    {
        return $this->stringList('scalar_properties');
    }

    /**
     * Log messages per reason. They are kept stable so log alerts keep matching.
     */
    private function describe(string $reason, Request $request): string
    {
        return match ($reason) {
            RequestBlocked::BLOCKED_IP => 'Blocked IP: '.$request->ip(),
            RequestBlocked::BLOCKED_USER_AGENT => 'Blocked User-Agent: '.$request->userAgent(),
            RequestBlocked::SUSPICIOUS_PAYLOAD => 'Suspicious Livewire payload detected',
            RequestBlocked::LOCKED_PROPERTY => 'Blocked Livewire property manipulation attempt',
            default => 'Blocked request: '.$reason,
        };
    }

    /**
     * Extract Livewire property updates from the payload formats Livewire sends.
     *
     * @param  array<int|string, mixed>  $data
     * @return array<int, array{property: string, value: mixed}>
     */
    private function extractPropertyUpdates(array $data): array
    {
        $result = [];

        $components = $data['components'] ?? null;

        if (is_array($components)) {
            foreach ($components as $component) {
                if (! is_array($component)) {
                    continue;
                }

                $updates = $component['updates'] ?? null;

                if (is_array($updates)) {
                    $result = array_merge($result, $this->normalizeUpdates($updates));
                }
            }
        }

        if (isset($data['updates']) && is_array($data['updates'])) {
            $result = array_merge($result, $this->normalizeUpdates($data['updates']));
        }

        return $result;
    }

    /**
     * Normalize Livewire update structures to property/value pairs.
     *
     * @param  array<int|string, mixed>  $updates
     * @return array<int, array{property: string, value: mixed}>
     */
    private function normalizeUpdates(array $updates): array
    {
        $normalized = [];

        foreach ($updates as $key => $value) {
            if (is_string($key)) {
                $normalized[] = ['property' => $key, 'value' => $value];

                continue;
            }

            if (! is_array($value)) {
                continue;
            }

            if (isset($value['name']) && is_string($value['name']) && array_key_exists('value', $value)) {
                $normalized[] = ['property' => $value['name'], 'value' => $value['value']];

                continue;
            }

            $payload = $value['payload'] ?? null;

            if (is_array($payload) && isset($payload['name']) && is_string($payload['name']) && array_key_exists('value', $payload)) {
                $normalized[] = ['property' => $payload['name'], 'value' => $payload['value']];
            }
        }

        return $normalized;
    }

    /**
     * Check if a property name suggests it should hold a scalar value.
     */
    private function looksLikeScalarProperty(string $propertyName): bool
    {
        $propertyLower = strtolower($propertyName);

        foreach (self::SCALAR_PREFIXES as $prefix) {
            if (str_starts_with($propertyLower, $prefix)) {
                return true;
            }
        }

        if (in_array($propertyLower, $this->scalarProperties(), true)) {
            return true;
        }

        // A name without a dot is a top-level property; nested keys (form.tags) are left alone.
        return $this->blocksAllArrayInjections() && ! str_contains($propertyName, '.');
    }

    /**
     * @return array<int, string>
     */
    private function stringList(string $key): array
    {
        $value = config('livewire-injection-stopper.'.$key, []);

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, 'is_string'));
    }
}
