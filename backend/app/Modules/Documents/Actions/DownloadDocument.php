<?php

namespace App\Modules\Documents\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Documents\Exceptions\DocumentDomainException;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Support\DocumentFileStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DownloadDocument
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly DocumentFileStore $files,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, Document $document, Request $request): StreamedResponse
    {
        $tenant = $this->tenantContext->require();

        if (! $this->files->exists($document)) {
            throw DocumentDomainException::fileMissing();
        }

        $this->security->record(AuthorizationSecurityEvent::DOCUMENT_DOWNLOADED, [
            'tenant_id' => $tenant->id,
            'actor_id' => $actor->id,
            'document_id' => $document->id,
            'document_number' => $document->document_number,
            'mime_type' => $document->mime_type,
            'size_bytes' => $document->size_bytes,
        ], $request);

        return Storage::disk($document->storage_disk)->download(
            $document->storage_path,
            $document->original_filename,
            [
                'Content-Type' => $document->mime_type,
            ],
        );
    }
}
