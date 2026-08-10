<?php

namespace App\Modules\Documents\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Documents\Enums\DocumentStatus;
use App\Modules\Documents\Exceptions\DocumentDomainException;
use App\Modules\Documents\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ArchiveDocument
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, Document $document, ?string $comment, Request $request): Document
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $document, $comment, $request, $tenant): Document {
            $locked = Document::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== DocumentStatus::Active) {
                throw DocumentDomainException::invalidStatusTransition();
            }

            $locked->status = DocumentStatus::Archived;
            $locked->archived_at = now();
            $locked->archived_by = $actor->id;
            $locked->save();

            $this->security->record(AuthorizationSecurityEvent::DOCUMENT_ARCHIVED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'document_id' => $locked->id,
                'document_number' => $locked->document_number,
                'comment' => $comment,
            ], $request);

            return $locked->load(['category', 'uploader', 'linkable', 'archiver']);
        });
    }
}
