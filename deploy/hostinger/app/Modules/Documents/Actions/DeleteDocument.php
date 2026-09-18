<?php

namespace App\Modules\Documents\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Support\DocumentFileStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class DeleteDocument
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly DocumentFileStore $files,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, Document $document, Request $request): void
    {
        $tenant = $this->tenantContext->require();

        $blob = null;

        DB::transaction(function () use ($actor, $document, $request, $tenant, &$blob): void {
            $locked = Document::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();

            $blob = [
                'disk' => $locked->storage_disk,
                'path' => $locked->storage_path,
            ];

            $snapshot = [
                'id' => $locked->id,
                'document_number' => $locked->document_number,
                'mime_type' => $locked->mime_type,
                'size_bytes' => $locked->size_bytes,
                'extension' => $locked->extension,
            ];

            $locked->delete();

            $this->security->record(AuthorizationSecurityEvent::DOCUMENT_DELETED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'document' => $snapshot,
            ], $request);
        });

        if (is_array($blob)) {
            $this->files->deletePath($blob['disk'], $blob['path']);
        }
    }
}
