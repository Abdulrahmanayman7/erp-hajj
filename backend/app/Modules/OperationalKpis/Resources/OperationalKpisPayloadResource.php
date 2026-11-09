<?php

namespace App\Modules\OperationalKpis\Resources;

use App\Modules\SeasonPilgrims\Enums\SeasonStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shapes the orchestrator payload into the S09 response `data` / `meta`.
 *
 * @property array<string, mixed> $resource
 */
class OperationalKpisPayloadResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $season = $this->resource['season'];

        return [
            'kpis' => array_map(
                static fn ($dto): array => (new OperationalKpiResource($dto))->resolve(),
                $this->resource['kpis'],
            ),
            'season' => [
                'id' => $season->id,
                'name' => $season->name,
                'status' => $season->status->value,
                'status_label' => self::statusLabel($season->status),
            ],
            'response_state' => $this->resource['response_state'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function metaArray(): array
    {
        return [
            'generated_at' => $this->resource['generated_at'],
            'timezone' => $this->resource['timezone'],
            'season_id' => $this->resource['season']->id,
        ];
    }

    private static function statusLabel(SeasonStatus $status): string
    {
        return match ($status) {
            SeasonStatus::Draft => 'مسودة',
            SeasonStatus::Active => 'نشط',
            SeasonStatus::Closed => 'مغلق',
        };
    }
}
