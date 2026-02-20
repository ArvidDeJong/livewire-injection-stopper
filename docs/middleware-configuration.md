# Middleware Configuration

## How It Works

The package includes middleware that automatically blocks requests based on:

1. **User-Agent Header** - Identifies and blocks automated tools and bots
2. **IP Address** - Blocks specific IP addresses you configure
3. **Route Whitelisting** - Allows certain routes to bypass checks (e.g., webhooks)
4. **Livewire Payload Inspection** - Detects suspicious array-injection attempts in Livewire update payloads

## Default Blocked User-Agents

Out of the box, the following patterns are blocked (case-insensitive):

- `python-requests` - Python HTTP library
- `curl` - Command-line HTTP tool
- `wget` - File download tool
- `scrapy` - Web scraping framework
- `bot`, `spider`, `crawler` - Generic bot patterns
- `postman`, `insomnia`, `httpie` - API testing tools

## Why Block These?

### Legitimate Use Cases
- API endpoints should be accessed via proper authentication
- Webhooks can be whitelisted
- Real browsers have different User-Agent strings

### Security Benefits
- Prevents automated form submissions
- Blocks scraping attempts
- Reduces spam and abuse
- Protects against reconnaissance attacks

## Customizing Blocked Agents

Edit `config/livewire-injection-stopper.php`:

```php
'blocked_user_agents' => [
    'python-requests',
    'my-custom-bot',
    // Add your patterns here
],
```

## Allowing Specific Tools

Comment out or remove patterns you want to allow:

```php
'blocked_user_agents' => [
    'python-requests',
    // 'curl',  // Now allowed
    // 'wget',  // Now allowed
    'bot',
],
```

## IP Blocking

Block specific IP addresses:

```php
'blocked_ips' => [
    '192.168.1.100',
    '10.0.0.50',
],
```

## Route Whitelisting

Exclude routes from checks (useful for webhooks):

```php
'whitelist_routes' => [
    'api/webhooks/*',
    'api/mollie-webhook',
    'api/stripe-webhook',
],
```

**Pattern Matching:**
- `api/webhooks/*` - Matches any route starting with `api/webhooks/`
- `api/webhook` - Exact match only

## Response Configuration

### Status Code

Choose the HTTP status code for blocked requests:

```php
// 403 = Forbidden (honest response)
'response_status' => 403,

// 404 = Not Found (misleads bots)
'response_status' => 404,
```

### Response Message

Customize the message shown to blocked users:

```php
'response_message' => 'Access Denied',
```

## Logging

Enable or disable logging of blocked requests:

```php
'log_blocked_requests' => true,
```

When enabled, blocked requests are logged as:

```
[2026-01-03 10:00:00] local.WARNING: [LivewireInjectionStopper] Blocked User-Agent: python-requests/2.28.0
{
    "ip": "123.45.67.89",
    "user_agent": "python-requests/2.28.0",
    "url": "https://example.com/contact",
    "method": "POST"
}
```

## Livewire Payload Injection Protection

When `check_payload_injection` is enabled, the middleware inspects Livewire update payloads and blocks suspicious array assignments to scalar/top-level properties.

This supports common Livewire payload formats, including component-based and operation-based `updates` structures.

Relevant settings in `config/livewire-injection-stopper.php`:

```php
'check_payload_injection' => true,
'block_all_array_injections' => true,
'scalar_properties' => [
    'content', 'email', 'status', // etc.
],
```

If a malicious payload still reaches Livewire and throws an exception, package exception silencing can still prevent Sentry noise.

## Manual Middleware Application

If you don't want global protection, you can apply the middleware selectively:

```php
// In routes/web.php
Route::middleware(['livewire-injection-stopper'])->group(function () {
    // Protected routes
});

// Or on specific routes
Route::post('/contact', ContactController::class)
    ->middleware('livewire-injection-stopper');
```

## Full Configuration Example

```php
return [
    'blocked_user_agents' => [
        'python-requests',
        'curl',
        'wget',
        'bot',
    ],
    
    'blocked_ips' => [
        '192.168.1.100',
    ],
    
    'whitelist_routes' => [
        'api/webhooks/*',
    ],
    
    'response_status' => 403,
    'response_message' => 'Access Denied',
    'log_blocked_requests' => true,
];
```
