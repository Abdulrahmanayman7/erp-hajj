<?php

namespace App\Modules\Documents\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Documents\Exceptions\DocumentDomainException;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class DeleteDocumentCategory
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, DocumentCategory $category, Request $request): void
    {
        $tenant = $this->tenantContext->require();

        DB::transaction(function () use ($actor, $category, $request, $tenant): void {
            $locked = DocumentCategory::query()->whereKey($category->id)->lockForUpdate()->firstOrFail();

            if (Document::query()->where('category_id', $locked->id)->exists()) {
                throw DocumentDomainException::categoryInUse();
            }

            $snapshot = [
                'id' => $locked->id,
                'name' => $locked->name,
            ];

            $locked->delete();

            $this->security->record(AuthorizationSecurityEvent::DOCUMENT_CATEGORY_DELETED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'document_category' => $snapshot,
            ], $request);
        });
    }
}
