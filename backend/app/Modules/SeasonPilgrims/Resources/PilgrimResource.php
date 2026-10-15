<?php

namespace App\Modules\SeasonPilgrims\Resources;

use App\Modules\SeasonPilgrims\Models\Pilgrim;
use App\Modules\SeasonPilgrims\Models\PilgrimIdentityDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Pilgrim
 */
class PilgrimResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Pilgrim $pilgrim */
        $pilgrim = $this->resource;

        $payload = [
            'id' => $pilgrim->id,
            'full_name' => $pilgrim->full_name,
            'gender' => $pilgrim->gender?->value,
            'nationality' => $pilgrim->nationality,
            'phone' => $pilgrim->phone,
            'notes' => $pilgrim->notes,
            'created_at' => $pilgrim->created_at?->toIso8601String(),
            'updated_at' => $pilgrim->updated_at?->toIso8601String(),
        ];

        if ($pilgrim->relationLoaded('identityDocuments')) {
            $payload['identity_documents'] = $pilgrim->identityDocuments
                ->map(fn (PilgrimIdentityDocument $d) => (new PilgrimIdentityDocumentResource($d))->resolve())
                ->values()
                ->all();
            $payload['has_identity_documents'] = $pilgrim->identityDocuments->isNotEmpty();
        }

        return $payload;
    }
}
