<?php

namespace App\Modules\Audit\Resources;

use App\Core\Audit\SensitiveFieldSanitizer;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Audit\Support\AuditDeepLinkResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AuditLog
 */
class AuditLogResource extends JsonResource
{
    public bool $withDetails = false;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var AuditLog $log */
        $log = $this->resource;

        $base = [
            'id' => $log->id,
            'event_type' => $log->event_type,
            'context_type' => $log->context_type,
            'actor_type' => $log->actor_type,
            'actor_user_id' => $log->actor_user_id,
            'actor_label' => $log->actor_label,
            'entity_type' => $log->entity_type,
            'entity_id' => $log->entity_id,
            'entity_number' => $log->entity_number,
            'entity_label' => $log->entity_label,
            'reason' => $log->reason,
            'correlation_id' => $log->correlation_id,
            'source' => $log->source,
            'created_at' => $log->created_at?->toIso8601String(),
        ];

        if (! $this->withDetails) {
            return $base;
        }

        $sanitizer = app(SensitiveFieldSanitizer::class);
        $deepLink = null;
        $user = $request->user();
        if ($user !== null) {
            $deepLink = app(AuditDeepLinkResolver::class)->resolve($user, $log);
        }

        return array_merge($base, [
            'metadata' => $sanitizer->sanitize($log->metadata),
            'before_values' => $sanitizer->sanitize($log->before_values),
            'after_values' => $sanitizer->sanitize($log->after_values),
            'ip_address' => $log->ip_address,
            'user_agent' => $log->user_agent,
            'deep_link' => $deepLink,
        ]);
    }
}
