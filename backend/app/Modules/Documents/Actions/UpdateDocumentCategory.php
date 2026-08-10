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

final class UpdateDocumentCategory
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, DocumentCategory $category, array $data, Request $request): DocumentCategory
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $category, $data, $request, $tenant): DocumentCategory {
            $locked = DocumentCategory::query()->whereKey($category->id)->lockForUpdate()->firstOrFail();

            if (array_key_exists('name', $data)) {
                $name = trim((string) $data['name']);
                if ($name === '') {
                    throw DocumentDomainException::categoryInvalid();
                }
                $taken = DocumentCategory::query()
                    ->where('name', $name)
                    ->whereKeyNot($locked->id)
                    ->exists();
                if ($taken) {
                    throw DocumentDomainException::categoryInvalid();
                }
                $locked->name = $name;
            }

            if (array_key_exists('description', $data)) {
                $locked->description = $data['description'];
            }

            if (array_key_exists('is_active', $data)) {
                $locked->is_active = (bool) $data['is_active'];
            }

            $locked->save();

            $this->security->record(AuthorizationSecurityEvent::DOCUMENT_CATEGORY_UPDATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'document_category_id' => $locked->id,
                'name' => $locked->name,
                'is_active' => $locked->is_active,
            ], $request);

            return $locked;
        });
    }
}
