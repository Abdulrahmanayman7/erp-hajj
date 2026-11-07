<?php

namespace App\Modules\RisksAndComplaints\Resources;

use App\Core\Audit\SensitiveFieldSanitizer;
use App\Modules\Audit\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Slim timeline entry — reuses audit_logs without exposing unauthorized/sensitive payloads.
 *
 * @mixin AuditLog
 */
class OperationalTimelineEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var AuditLog $log */
        $log = $this->resource;
        $sanitizer = app(SensitiveFieldSanitizer::class);

        $metadata = is_array($log->metadata) ? $sanitizer->sanitize($log->metadata) : null;
        if (is_array($metadata)) {
            unset($metadata['body'], $metadata['resolution_text'], $metadata['close_reason'], $metadata['reopen_reason']);
            if (isset($metadata['after']) && is_array($metadata['after'])) {
                unset(
                    $metadata['after']['body'],
                    $metadata['after']['resolution_text'],
                    $metadata['after']['close_reason'],
                    $metadata['after']['reopen_reason'],
                );
            }
            if (isset($metadata['before']) && is_array($metadata['before'])) {
                unset(
                    $metadata['before']['body'],
                    $metadata['before']['resolution_text'],
                    $metadata['before']['close_reason'],
                    $metadata['before']['reopen_reason'],
                );
            }
        }

        return [
            'id' => $log->id,
            'event_type' => $log->event_type,
            'actor_label' => $log->actor_label,
            'actor_user_id' => $log->actor_user_id,
            'entity_type' => $log->entity_type,
            'entity_id' => $log->entity_id,
            'entity_number' => $log->entity_number,
            'correlation_id' => $log->correlation_id,
            'created_at' => $log->created_at?->toIso8601String(),
            'summary' => $metadata,
        ];
    }
}
