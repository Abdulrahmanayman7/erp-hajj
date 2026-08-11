<?php

namespace App\Modules\Assets\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Assets\Exceptions\AssetDomainException;
use App\Modules\Assets\Models\Asset;
use App\Modules\Assets\Models\AssetCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class DeleteAssetCategory
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, AssetCategory $category, Request $request): void
    {
        $tenant = $this->tenantContext->require();

        DB::transaction(function () use ($actor, $category, $request, $tenant): void {
            /** @var AssetCategory $locked */
            $locked = AssetCategory::query()->whereKey($category->id)->lockForUpdate()->firstOrFail();

            if (Asset::query()->where('category_id', $locked->id)->exists()) {
                throw AssetDomainException::categoryInUse();
            }

            $snapshot = [
                'id' => $locked->id,
                'name' => $locked->name,
            ];

            $locked->delete();

            $this->security->record(AuthorizationSecurityEvent::ASSET_CATEGORY_DELETED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'asset_category' => $snapshot,
            ], $request);
        });
    }
}
