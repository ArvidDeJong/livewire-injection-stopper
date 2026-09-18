<?php

declare(strict_types=1);

namespace Darvis\LivewireInjectionStopper\Events;

use Throwable;

/**
 * Dispatched right before a request is rejected, so the host app can log or count blocked attempts.
 */
final class RequestBlocked
{
    public const BLOCKED_IP = 'blocked_ip';

    public const BLOCKED_USER_AGENT = 'blocked_user_agent';

    public const SUSPICIOUS_PAYLOAD = 'suspicious_payload';

    public const LOCKED_PROPERTY = 'locked_property';

    public function __construct(
        public readonly string $reason,
        public readonly ?string $ip,
        public readonly ?string $userAgent,
        public readonly string $url,
        public readonly ?Throwable $exception = null,
    ) {}
}
