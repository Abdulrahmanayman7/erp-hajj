<?php

namespace App\Modules\ExecutiveDashboard\Resources;

use App\Modules\OperationalKpis\Resources\OperationalKpiResource;
use App\Modules\SeasonPilgrims\Enums\SeasonStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array<string, mixed> $resource
 */
class ExecutiveDashboardPayloadResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $season = $this->resource['season'];

        $data = [
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

        if (array_key_exists('gps', $this->resource)) {
            $data['gps'] = $this->resource['gps'];
        }

        return $data;
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
