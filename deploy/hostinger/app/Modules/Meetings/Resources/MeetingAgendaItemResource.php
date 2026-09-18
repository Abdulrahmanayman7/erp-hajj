<?php

namespace App\Modules\Meetings\Resources;

use App\Modules\Meetings\Models\MeetingAgendaItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MeetingAgendaItem
 */
class MeetingAgendaItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var MeetingAgendaItem $row */
        $row = $this->resource;

        return [
            'id' => $row->id,
            'title' => $row->title,
            'description' => $row->description,
            'sort_order' => $row->sort_order,
        ];
    }
}
