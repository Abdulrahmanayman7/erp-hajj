<?php

namespace App\Core\Auth\Support;

use App\Core\Auth\Events\AuthSecurityEvent;
use App\Core\Shared\CorrelationId;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

/**
 * Dispatches sanitized auth security events (no secrets).
 */
final class AuthSecurity
{
    public function __construct(private readonly CorrelationId $correlationId) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function record(string $event, Request $request, array $context = []): void
    {
        Event::dispatch(new AuthSecurityEvent($event, [
            'correlation_id' => $this->correlationId->get(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            ...$context,
        ]));
    }
}
