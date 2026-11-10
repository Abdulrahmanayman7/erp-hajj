<?php

namespace App\Modules\ExecutiveDashboard\Resources;

use App\Modules\ExecutiveAlerts\Resources\ExecutiveAlertResource;
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

        $seasonPayload = [
            'id' => $season->id,
            'name' => $season->name,
            'status' => $season->status->value,
            'status_label' => self::statusLabel($season->status),
        ];

        $data = [
            'kpis' => array_map(
                static fn ($dto): array => (new OperationalKpiResource($dto))->resolve(),
                $this->resource['kpis'],
            ),
            'season' => $seasonPayload,
            'response_state' => $this->resource['response_state'],
            'panels' => $this->panelsArray($seasonPayload),
        ];

        if (array_key_exists('gps', $this->resource)) {
            $data['gps'] = $this->resource['gps'];
        }

        return $data;
    }

    /**
     * @param  array{id: int, name: string, status: string, status_label: string}  $seasonPayload
     * @return array<string, mixed>
     */
    private function panelsArray(array $seasonPayload): array
    {
        /** @var array<string, mixed> $raw */
        $raw = $this->resource['panels'] ?? [];

        $panels = [
            'season' => $seasonPayload,
        ];

        if (isset($raw['accommodation']['kpis']) && is_array($raw['accommodation']['kpis']) && $raw['accommodation']['kpis'] !== []) {
            $panels['accommodation'] = [
                'kpis' => array_map(
                    static fn ($dto): array => (new OperationalKpiResource($dto))->resolve(),
                    $raw['accommodation']['kpis'],
                ),
                'occupancy_percent' => $raw['accommodation']['occupancy_percent'] ?? null,
                'occupancy_state' => $raw['accommodation']['occupancy_state'] ?? 'unavailable',
                'occupancy_unavailable_reason' => $raw['accommodation']['occupancy_unavailable_reason'] ?? null,
                'occupancy_denominator' => $raw['accommodation']['occupancy_denominator'] ?? null,
            ];
        }

        if (isset($raw['projects']['kpis']) && is_array($raw['projects']['kpis']) && $raw['projects']['kpis'] !== []) {
            $panels['projects'] = [
                'kpis' => array_map(
                    static fn ($dto): array => (new OperationalKpiResource($dto))->resolve(),
                    $raw['projects']['kpis'],
                ),
            ];
        }

        if (isset($raw['alerts']) && is_array($raw['alerts'])) {
            $panels['alerts'] = [
                'open_count' => (int) $raw['alerts']['open_count'],
                'acknowledged_count' => (int) $raw['alerts']['acknowledged_count'],
                'critical_count' => (int) $raw['alerts']['critical_count'],
                'items' => array_map(
                    static fn ($alert): array => (new ExecutiveAlertResource($alert))->resolve(),
                    $raw['alerts']['items'],
                ),
            ];
        }

        return $panels;
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
