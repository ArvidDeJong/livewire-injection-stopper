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
        'python-requests',
        'python/requests',
        'curl',
        'wget',
        'scrapy',
        'bot',
        'spider',
        'crawler',
        'scraper',
        'postman',
        'insomnia',
        'httpie',
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
];
