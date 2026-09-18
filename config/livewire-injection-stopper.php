<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Allowed User Agents
    |--------------------------------------------------------------------------
    |
    | User agents that always pass, even when they match a blocked pattern.
    | Add your uptime monitor here before you block a generic word.
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
    | Block All Array Injections
    |--------------------------------------------------------------------------
    |
    | When enabled, every array sent to a top-level Livewire property is
    | blocked, not only arrays sent to the known scalar names below. Nested
    | keys such as form.tags are never blocked. Disable this if a component
    | binds an array property (a multi-select, checkboxes) at the top level.
    |
    */
    'block_all_array_injections' => true,

    /*
    |--------------------------------------------------------------------------
    | Blocked IP Addresses
    |--------------------------------------------------------------------------
    |
    | Exact IP addresses that are blocked.
    |
    */
    'blocked_ips' => [
        // '203.0.113.42',
    ],

    /*
    |--------------------------------------------------------------------------
    | Blocked User Agents
    |--------------------------------------------------------------------------
    |
    | User-Agent patterns that are blocked. Matching is case-insensitive and
    | uses str_contains(), so 'python' blocks python-requests, python-urllib
    | and every other client that names Python. Search engines are not listed
    | on purpose; generic words like 'bot' would block them too.
    |
    */
    'blocked_user_agents' => [
        // HTTP clients and scripts
        'python',
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

        // SEO and AI crawlers (not search engines)
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
    | Payload Injection
    |--------------------------------------------------------------------------
    |
    | Inspect Livewire update requests for arrays sent to properties that
    | should hold a scalar value (type confusion attacks).
    |
    */
    'check_payload_injection' => true,

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Write a warning to the default log channel for every blocked request.
    | The RequestBlocked event is dispatched either way.
    |
    */
    'log_blocked_requests' => true,

    /*
    |--------------------------------------------------------------------------
    | Response Message
    |--------------------------------------------------------------------------
    |
    | Body of the response for a blocked request.
    |
    */
    'response_message' => 'Access Denied',

    /*
    |--------------------------------------------------------------------------
    | Response Status
    |--------------------------------------------------------------------------
    |
    | Status code of the response for a blocked request.
    | 403 = Forbidden, 404 = Not Found (to mislead bots).
    |
    */
    'response_status' => 403,

    /*
    |--------------------------------------------------------------------------
    | Known Scalar Properties
    |--------------------------------------------------------------------------
    |
    | Property names that never hold an array. Names starting with is_, has_,
    | show_, can_, should_, enable, disable, active, visible or hidden are
    | treated the same way.
    |
    */
    'scalar_properties' => [
        'style', 'class', 'id', 'name', 'title', 'label', 'value', 'text', 'content',
        'description', 'placeholder', 'type', 'status', 'state', 'mode', 'color',
        'size', 'width', 'height', 'url', 'href', 'src', 'alt', 'icon', 'image',
        'email', 'phone', 'address', 'message', 'subject', 'body', 'slug', 'path',
    ],

    /*
    |--------------------------------------------------------------------------
    | Silence Locked Property Exceptions
    |--------------------------------------------------------------------------
    |
    | Answer CannotUpdateLockedPropertyException, and TypeErrors raised while
    | Livewire assigns an array to a typed property, with the block response
    | instead of reporting them to Sentry or another error tracker. The
    | attempt is still logged (if log_blocked_requests is true) and dispatches
    | the RequestBlocked event.
    |
    */
    'silence_locked_property_exceptions' => true,

    /*
    |--------------------------------------------------------------------------
    | Whitelisted Routes
    |--------------------------------------------------------------------------
    |
    | Paths that skip every check, for example webhooks that a service calls
    | with a scripted client. Patterns use fnmatch() against the request path
    | without a leading slash.
    |
    */
    'whitelist_routes' => [
        'api/mollie-webhook',
        'api/webhooks/*',
    ],
];
