<?php

namespace App\Modules\SuppliersAndProcurement\Resources;

use App\Modules\SuppliersAndProcurement\Models\SupplierDocumentRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * No storage disk/path or file metadata is exposed (downloads go through Documents policies).
 *
 * @mixin SupplierDocumentRecord
 */
class SupplierDocumentRecordResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'supplier_id' => $this->supplier_id,
            'requirement_def_id' => $this->requirement_def_id,
            'requirement_key' => $this->relationLoaded('requirementDef') ? $this->requirementDef?->requirement_key : null,
            'document_id' => $this->document_id,
            'document_number' => $this->relationLoaded('document') ? $this->document?->document_number : null,
            'verification_status' => $this->verification_status->value,
            'expires_at' => $this->expires_at?->format('Y-m-d'),
            'uploaded_by' => $this->userRef('uploader', $this->uploaded_by),
            'verified_by' => $this->userRef('verifier', $this->verified_by),
            'verified_at' => $this->verified_at?->toIso8601String(),
            'rejection_reason' => $this->rejection_reason,
            'supersedes_id' => $this->supersedes_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function userRef(string $relation, mixed $id): ?array
    {
        if ($id === null) {
            return null;
        }

        $user = $this->relationLoaded($relation) ? $this->{$relation} : null;

        return ['id' => (int) $id, 'name' => $user?->name ?? ''];
    }
}
