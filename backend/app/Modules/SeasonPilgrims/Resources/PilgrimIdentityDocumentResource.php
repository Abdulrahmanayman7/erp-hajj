<?php

namespace App\Modules\SeasonPilgrims\Resources;

use App\Modules\SeasonPilgrims\Models\PilgrimIdentityDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PilgrimIdentityDocument
 */
class PilgrimIdentityDocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PilgrimIdentityDocument $document */
        $document = $this->resource;

        return [
            'id' => $document->id,
            'pilgrim_id' => $document->pilgrim_id,
            'document_type' => $document->document_type->value,
            'issuing_country' => $document->issuing_country,
            'document_number' => $document->document_number,
            'created_at' => $document->created_at?->toIso8601String(),
            'updated_at' => $document->updated_at?->toIso8601String(),
        ];
    }
}
