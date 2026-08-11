<?php

namespace App\Modules\Documents\Resources;

use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Support\DocumentReferenceValidator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Document
 */
class DocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Document $document */
        $document = $this->resource;

        return [
            'id' => $document->id,
            'document_number' => $document->document_number,
            'title' => $document->title,
            'description' => $document->description,
            'status' => $document->status->value,
            'original_filename' => $document->original_filename,
            'mime_type' => $document->mime_type,
            'extension' => $document->extension,
            'size_bytes' => $document->size_bytes,
            'checksum_sha256' => $document->checksum_sha256,
            'category' => $this->categoryPayload($document),
            'link' => $this->linkPayload($document),
            'uploaded_by' => $this->uploaderPayload($document),
            'archived_at' => $document->archived_at?->toIso8601String(),
            'archived_by' => $this->archiverPayload($document),
            'created_at' => $document->created_at?->toIso8601String(),
            'updated_at' => $document->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{id: int, name: string, is_active: bool}|null
     */
    private function categoryPayload(Document $document): ?array
    {
        if (! $document->relationLoaded('category') || $document->category === null) {
            return null;
        }

        return [
            'id' => $document->category->id,
            'name' => $document->category->name,
            'is_active' => (bool) $document->category->is_active,
        ];
    }

    /**
     * @return array{type: string, id: int, label: string|null}|null
     */
    private function linkPayload(Document $document): ?array
    {
        if ($document->linkable_type === null || $document->linkable_id === null) {
            return null;
        }

        $loaded = $document->relationLoaded('linkable') ? $document->linkable : null;
        $label = app(DocumentReferenceValidator::class)->linkLabel(
            $document->linkable_type,
            $document->linkable_id,
            $loaded,
        );

        return [
            'type' => $document->linkable_type,
            'id' => $document->linkable_id,
            'label' => $label,
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function uploaderPayload(Document $document): ?array
    {
        if (! $document->relationLoaded('uploader') || $document->uploader === null) {
            return null;
        }

        return [
            'id' => $document->uploader->id,
            'name' => $document->uploader->name,
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function archiverPayload(Document $document): ?array
    {
        if (! $document->relationLoaded('archiver') || $document->archiver === null) {
            return null;
        }

        return [
            'id' => $document->archiver->id,
            'name' => $document->archiver->name,
        ];
    }
}
