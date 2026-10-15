<?php

namespace App\Modules\SeasonPilgrims\Resources;

use App\Modules\SeasonPilgrims\Models\Season;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Season
 */
class SeasonResource extends JsonResource
{
    /**
     * Operational window dates are Gregorian calendar dates (F11, F12);
     * lifecycle timestamps are UTC ISO-8601.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Season $season */
        $season = $this->resource;

        return [
            'id' => $season->id,
            'code' => $season->code,
            'name' => $season->name,
            'hajj_year' => $season->hajj_year,
            'status' => $season->status->value,
            'operational_starts_on' => $season->operational_starts_on->toDateString(),
            'operational_ends_on' => $season->operational_ends_on->toDateString(),
            'notes' => $season->notes,
            'first_activated_at' => $season->first_activated_at?->toIso8601String(),
            'activated_at' => $season->activated_at?->toIso8601String(),
            'closed_at' => $season->closed_at?->toIso8601String(),
            'reopened_at' => $season->reopened_at?->toIso8601String(),
            'created_at' => $season->created_at?->toIso8601String(),
            'updated_at' => $season->updated_at?->toIso8601String(),
        ];
    }
}
