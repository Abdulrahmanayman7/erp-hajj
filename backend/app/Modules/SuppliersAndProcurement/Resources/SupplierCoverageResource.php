<?php

namespace App\Modules\SuppliersAndProcurement\Resources;

use App\Modules\SuppliersAndProcurement\Models\SupplierCategoryCoverage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SupplierCategoryCoverage */
class SupplierCoverageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'supplier_category_id' => $this->supplier_category_id,
            'geo_place_id' => $this->geo_place_id,
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ]),
            'geo_place' => $this->whenLoaded('geoPlace', fn () => (new GeoPlaceResource($this->geoPlace))->resolve()),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
