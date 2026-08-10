<?php

namespace App\Modules\Documents\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Documents\Exceptions\DocumentDomainException;
use App\Modules\Documents\Models\DocumentCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CreateDocumentCategory
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array{name: string, description?: string|null, is_active?: bool|null}  $data
     */
    public function execute(User $actor, array $data, Request $request): DocumentCategory
    {
        $tenant = $this->tenantContext->require();
        $name = trim((string) $data['name']);

        return DB::transaction(function () use ($actor, $data, $request, $tenant, $name): DocumentCategory {
            if (DocumentCategory::query()->where('name', $name)->exists()) {
                throw DocumentDomainException::categoryInvalid();
            }

            $category = DocumentCategory::query()->create([
                'name' => $name,
                'description' => $data['description'] ?? null,
                'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
            ]);

            $this->security->record(AuthorizationSecurityEvent::DOCUMENT_CATEGORY_CREATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'document_category_id' => $category->id,
                'name' => $category->name,
            ], $request);

            return $category;
        });
    }
}
