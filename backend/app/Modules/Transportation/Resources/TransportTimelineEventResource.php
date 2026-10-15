<?php

namespace App\Modules\Transportation\Resources;

use App\Modules\Transportation\Models\TransportTimelineEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TransportTimelineEvent
 */
class TransportTimelineEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var TransportTimelineEvent $event */
        $event = $this->resource;

        return [
            'id' => $event->id,
            'transport_movement_id' => $event->transport_movement_id,
            'vehicle_run_id' => $event->vehicle_run_id,
            'run_passenger_id' => $event->run_passenger_id,
            'event_type' => $event->event_type->value,
            'occurred_at' => $event->occurred_at?->toIso8601String(),
            'recorded_at' => $event->recorded_at?->toIso8601String(),
            'actor' => $this->whenLoaded('actor', fn () => $event->actor === null ? null : ['id' => $event->actor->id, 'name' => $event->actor->name]),
            'reason' => $event->reason,
            'context' => $event->context,
            'supersedes_event_id' => $event->supersedes_event_id,
            'superseded_by_event_id' => $event->superseded_by_event_id ?? null,
            'correlation_id' => $event->correlation_id,
        ];
    }
}
