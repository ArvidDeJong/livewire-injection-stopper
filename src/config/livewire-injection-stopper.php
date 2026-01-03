<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Blocked User Agents
    |--------------------------------------------------------------------------
    |
    | List of user agent patterns that should be blocked.
    | These patterns are checked case-insensitively.
    |
    */
    'blocked_user_agents' => [
        // HTTP clients / scripts (excluding monitoring tools)
        'python-requests',
        'python/requests',
        'python requests',
        'python-urllib',
        'aiohttp',
        'httpx',
        'curl/',
        'wget',
        'scrapy',
        'postman',
        'insomnia',
        'httpie',
        'go-http-client',
        'java/',
        'okhttp',
        'axios',
        'node-fetch',
        'libwww-perl',

        // Malicious/unwanted bots (NOT search engines)
        'ahrefsbot',
        'semrushbot',
        'dotbot',
        'mj12bot',
        'blexbot',
        'dataforseo',
        'bytespider',
        'petalbot',
        'gptbot',
        'claudebot',
        'ccbot',
        'anthropic',
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed User Agents (Whitelist)
    |--------------------------------------------------------------------------
    |
    | User agents that should always be allowed, even if they match blocked patterns.
    | Useful for monitoring tools like Sentry Uptime.
    |
    */
    'allowed_user_agents' => [
        'sentryuptimebot',
        'uptimerobot',
        'pingdom',
        'statuscake',
    ],

    /*
    |--------------------------------------------------------------------------
    | Blocked IP Addresses
    |--------------------------------------------------------------------------
    |
    | List of IP addresses that should be blocked.
    |
    */
    'blocked_ips' => [
        // Add specific IP addresses here
    ],

    /*
    |--------------------------------------------------------------------------
    | Whitelist Routes
    |--------------------------------------------------------------------------
    |
    | Routes that should be excluded from the checks.
    | For example, API endpoints that should remain accessible.
    |
    */
    'whitelist_routes' => [
        'api/mollie-webhook',
        'api/webhooks/*',
    ],

    /*
    |--------------------------------------------------------------------------
    | Response Status Code
    |--------------------------------------------------------------------------
    |
    | HTTP status code returned for blocked requests.
    | 403 = Forbidden, 404 = Not Found (to mislead bots)
    |
    */
    'response_status' => 403,

    /*
    |--------------------------------------------------------------------------
    | Response Message
    |--------------------------------------------------------------------------
    |
    | Message shown for blocked requests.
    |
    */
    'response_message' => 'Access Denied',

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Whether blocked requests should be logged.
    |
    */
    'log_blocked_requests' => true,

    /*
    |--------------------------------------------------------------------------
    | Check Payload Injection
    |--------------------------------------------------------------------------
    |
    | Whether to check Livewire update payloads for suspicious data.
    | This detects attempts to inject arrays into scalar properties.
    |
    */
    'check_payload_injection' => true,

    /*
    |--------------------------------------------------------------------------
    | Block All Array Injections
    |--------------------------------------------------------------------------
    |
    | When enabled, blocks ALL array values sent to top-level Livewire properties.
    | This is aggressive but catches type confusion attacks where attackers
    | try to inject arrays into string/bool/int properties.
    |
    */
    'block_all_array_injections' => true,

    /*
    |--------------------------------------------------------------------------
    | Known Scalar Properties
    |--------------------------------------------------------------------------
    |
    | Property names that should always be scalar values (string, int, bool).
    | Arrays sent to these properties will be blocked.
    |
    */
    'scalar_properties' => [
        'style', 'class', 'id', 'name', 'title', 'label', 'value', 'text', 'content',
        'description', 'placeholder', 'type', 'status', 'state', 'mode', 'color',
        'size', 'width', 'height', 'url', 'href', 'src', 'alt', 'icon', 'image',
        'email', 'phone', 'address', 'message', 'subject', 'body', 'slug', 'path',
    ],
];
