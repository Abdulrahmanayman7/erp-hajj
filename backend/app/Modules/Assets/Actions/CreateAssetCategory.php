<?php

namespace App\Modules\Assets\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Assets\Exceptions\AssetDomainException;
use App\Modules\Assets\Models\AssetCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CreateAssetCategory
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array{name: string, description?: string|null, is_active?: bool|null}  $data
     */
    public function execute(User $actor, array $data, Request $request): AssetCategory
    {
        $tenant = $this->tenantContext->require();
        $name = trim((string) $data['name']);

        return DB::transaction(function () use ($actor, $data, $request, $tenant, $name): AssetCategory {
            if (AssetCategory::query()->where('name', $name)->exists()) {
                throw AssetDomainException::categoryNameTaken();
            }

            $category = AssetCategory::query()->create([
                'name' => $name,
                'description' => $data['description'] ?? null,
                'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
            ]);

            $this->security->record(AuthorizationSecurityEvent::ASSET_CATEGORY_CREATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'asset_category_id' => $category->id,
                'name' => $category->name,
            ], $request);

            return $category;
        });
    }
}
