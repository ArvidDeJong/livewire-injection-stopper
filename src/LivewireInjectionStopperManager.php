<?php

declare(strict_types=1);

namespace Darvis\LivewireInjectionStopper;

use Illuminate\Http\Request;

/**
 * Main manager class for livewire injection stopper functionality.
 * Provides a clean API for checking blocked user agents, IPs, and suspicious payloads.
 */
final class LivewireInjectionStopperManager
{
    /**
     * Check if the User-Agent is blocked.
     */
    public function isBlockedUserAgent(?string $userAgent): bool
    {
        if ($userAgent === null) {
            return false;
        }

        // Check if user agent is whitelisted first
        $allowedAgents = config('livewire-injection-stopper.allowed_user_agents', []);
        $userAgentLower = strtolower($userAgent);

        foreach ($allowedAgents as $pattern) {
            if (str_contains($userAgentLower, strtolower($pattern))) {
                return false;
            }
        }

        $blockedAgents = config('livewire-injection-stopper.blocked_user_agents', []);

        foreach ($blockedAgents as $pattern) {
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
        if ($ip === null) {
            return false;
        }

        $blockedIps = config('livewire-injection-stopper.blocked_ips', []);

        return in_array($ip, $blockedIps, true);
    }

    /**
     * Check if the route is whitelisted.
     */
    public function isWhitelisted(string $route): bool
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
     * Check if the request contains suspicious Livewire payload.
     */
    public function hasSuspiciousPayload(Request $request): bool
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

        foreach ($this->extractPropertyUpdates($data) as $update) {
            $property = $update['property'];
            $value = $update['value'];

            if (is_array($value) && $this->looksLikeScalarProperty($property)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract Livewire property updates from different payload formats.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, array{property: string, value: mixed}>
     */
    private function extractPropertyUpdates(array $data): array
    {
        $result = [];

        $components = $data['components'] ?? null;
        if (is_array($components)) {
            foreach ($components as $component) {
                if (!is_array($component)) {
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
     * @param  array<string|int, mixed>  $updates
     * @return array<int, array{property: string, value: mixed}>
     */
    private function normalizeUpdates(array $updates): array
    {
        $normalized = [];

        foreach ($updates as $key => $value) {
            if (is_string($key)) {
                $normalized[] = [
                    'property' => $key,
                    'value' => $value,
                ];

                continue;
            }

            if (!is_array($value)) {
                continue;
            }

            if (isset($value['name']) && is_string($value['name']) && array_key_exists('value', $value)) {
                $normalized[] = [
                    'property' => $value['name'],
                    'value' => $value['value'],
                ];

                continue;
            }

            $payload = $value['payload'] ?? null;
            if (is_array($payload) && isset($payload['name']) && is_string($payload['name']) && array_key_exists('value', $payload)) {
                $normalized[] = [
                    'property' => $payload['name'],
                    'value' => $payload['value'],
                ];
            }
        }

        return $normalized;
    }

    /**
     * Check if a property name suggests it should be a scalar value.
     */
    private function looksLikeScalarProperty(string $propertyName): bool
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

        if (in_array($propertyLower, $scalarProperties, true)) {
            return true;
        }

        // Block arrays to simple property names (no dots = top-level property)
        if (config('livewire-injection-stopper.block_all_array_injections', true)) {
            if (!str_contains($propertyName, '.')) {
                return true;
            }
        }

        return false;
    }
}
