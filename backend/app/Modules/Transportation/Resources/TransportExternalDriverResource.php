<?php

namespace App\Modules\Transportation\Resources;

use App\Modules\Transportation\Models\TransportExternalDriver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TransportExternalDriver
 */
class TransportExternalDriverResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var TransportExternalDriver $driver */
        $driver = $this->resource;

        return [
            'id' => $driver->id,
            'full_name' => $driver->full_name,
            'phone' => $driver->phone,
            'is_active' => (bool) $driver->is_active,
            'notes' => $driver->notes,
            'created_at' => $driver->created_at?->toIso8601String(),
            'updated_at' => $driver->updated_at?->toIso8601String(),
        ];
    }
}
