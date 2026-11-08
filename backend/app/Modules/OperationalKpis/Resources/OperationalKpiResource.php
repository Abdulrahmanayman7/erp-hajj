<?php

namespace App\Modules\OperationalKpis\Resources;

use App\Modules\OperationalKpis\Support\KpiDto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property KpiDto $resource
 */
class OperationalKpiResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'domain' => $this->resource->domain,
            'label' => $this->resource->labelAr,
            'value' => $this->resource->value,
            'display_value' => $this->resource->displayValue,
            'state' => $this->resource->state->value,
            'href' => $this->resource->drilldownPath,
        ];
    }
}
