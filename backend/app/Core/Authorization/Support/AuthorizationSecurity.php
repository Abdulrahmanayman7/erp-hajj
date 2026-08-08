<?php

namespace App\Core\Authorization\Support;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Shared\CorrelationId;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

/**
 * Dispatches sanitized authorization events (no passwords/tokens).
 */
final class AuthorizationSecurity
{
    public function __construct(private readonly CorrelationId $correlationId) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function record(string $event, array $context = [], ?Request $request = null): void
    {
        $payload = [
            'correlation_id' => $this->correlationId->get(),
            ...$context,
        ];

        if ($request !== null) {
            $payload['ip'] = $request->ip();
            $payload['user_agent'] = $request->userAgent();
        }

        Event::dispatch(new AuthorizationSecurityEvent($event, $payload));
    }
}
