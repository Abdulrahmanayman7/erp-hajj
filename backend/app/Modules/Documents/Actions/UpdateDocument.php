<?php

namespace App\Modules\Documents\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Documents\Exceptions\DocumentDomainException;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Support\DocumentReferenceValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class UpdateDocument
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly DocumentReferenceValidator $references,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, Document $document, array $data, Request $request): Document
    {
        $tenant = $this->tenantContext->require();

        foreach ([
            'document_number',
            'tenant_id',
            'stored_filename',
            'storage_disk',
            'storage_path',
            'mime_type',
            'extension',
            'size_bytes',
            'checksum_sha256',
            'original_filename',
            'uploaded_by',
            'status',
        ] as $immutable) {
            if (array_key_exists($immutable, $data)) {
                throw DocumentDomainException::immutable();
            }
        }

        return DB::transaction(function () use ($actor, $document, $data, $request, $tenant): Document {
            $locked = Document::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();

            $previousType = $locked->linkable_type;
            $previousId = $locked->linkable_id;

            if (array_key_exists('title', $data)) {
                $locked->title = mb_substr(trim((string) $data['title']), 0, 255);
            }

            if (array_key_exists('description', $data)) {
                $locked->description = $data['description'];
            }

            if (array_key_exists('category_id', $data)) {
                $categoryId = $data['category_id'] !== null && $data['category_id'] !== ''
                    ? (int) $data['category_id']
                    : null;
                $category = $this->references->resolveCategory($categoryId, $locked->category_id);
                $locked->category_id = $category?->id;
            }

            if (array_key_exists('linkable_type', $data) || array_key_exists('linkable_id', $data)) {
                $type = array_key_exists('linkable_type', $data)
                    ? ($data['linkable_type'] !== null ? (string) $data['linkable_type'] : null)
                    : $locked->linkable_type;
                $id = array_key_exists('linkable_id', $data)
                    ? $data['linkable_id']
                    : $locked->linkable_id;

                [$linkableType, $linkableId] = $this->references->resolveLink($type, $id);
                $locked->linkable_type = $linkableType;
                $locked->linkable_id = $linkableId;
            }

            $locked->save();

            $this->security->record(AuthorizationSecurityEvent::DOCUMENT_UPDATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'document_id' => $locked->id,
                'document_number' => $locked->document_number,
            ], $request);

            $linkChanged = $previousType !== $locked->linkable_type || (int) ($previousId ?? 0) !== (int) ($locked->linkable_id ?? 0);
            if ($linkChanged) {
                if ($previousType !== null) {
                    $this->security->record(AuthorizationSecurityEvent::DOCUMENT_UNLINKED, [
                        'tenant_id' => $tenant->id,
                        'actor_id' => $actor->id,
                        'document_id' => $locked->id,
                        'document_number' => $locked->document_number,
                        'linkable_type' => $previousType,
                        'linkable_id' => $previousId,
                    ], $request);
                }
                if ($locked->linkable_type !== null) {
                    $this->security->record(AuthorizationSecurityEvent::DOCUMENT_LINKED, [
                        'tenant_id' => $tenant->id,
                        'actor_id' => $actor->id,
                        'document_id' => $locked->id,
                        'document_number' => $locked->document_number,
                        'linkable_type' => $locked->linkable_type,
                        'linkable_id' => $locked->linkable_id,
                    ], $request);
                }
            }

            return $locked->load(['category', 'uploader', 'linkable', 'archiver']);
        });
    }
}
