<?php

namespace App\Modules\Meetings\Resources;

use App\Modules\Meetings\Models\MeetingRecommendation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MeetingRecommendation
 */
class MeetingRecommendationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var MeetingRecommendation $row */
        $row = $this->resource;

        $owner = null;
        if ($row->relationLoaded('owner') && $row->owner !== null) {
            $owner = [
                'id' => $row->owner->id,
                'employee_number' => $row->owner->employee_number,
                'full_name' => $row->owner->full_name,
            ];
        }

        $createdBy = null;
        if ($row->relationLoaded('createdBy') && $row->createdBy !== null) {
            $createdBy = [
                'id' => $row->createdBy->id,
                'name' => $row->createdBy->name,
            ];
        }

        $linked = null;
        if ($row->relationLoaded('decision') && $row->decision !== null) {
            $linked = [
                'id' => $row->decision->id,
                'decision_number' => $row->decision->decision_number,
                'status' => $row->decision->status->value,
            ];
        }

        return [
            'id' => $row->id,
            'title' => $row->title,
            'description' => $row->description,
            'status' => $row->status->value,
            'sort_order' => $row->sort_order,
            'agenda_item_id' => $row->agenda_item_id,
            'owner' => $owner,
            'created_by' => $createdBy,
            'linked_decision' => $linked,
        ];
    }
}
