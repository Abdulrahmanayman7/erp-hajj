<?php

namespace App\Modules\Inventory\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Inventory\Exceptions\InventoryDomainException;
use App\Modules\Inventory\Models\InventoryCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CreateInventoryCategory
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array{name: string, description?: string|null, is_active?: bool|null}  $data
     */
    public function execute(User $actor, array $data, Request $request): InventoryCategory
    {
        $tenant = $this->tenantContext->require();
        $name = trim((string) $data['name']);

        return DB::transaction(function () use ($actor, $data, $request, $tenant, $name): InventoryCategory {
            if (InventoryCategory::query()->where('name', $name)->exists()) {
                throw InventoryDomainException::categoryNameTaken();
            }

            $category = InventoryCategory::query()->create([
                'name' => $name,
                'description' => $data['description'] ?? null,
                'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
            ]);

            $this->security->record(AuthorizationSecurityEvent::INVENTORY_CATEGORY_CREATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'inventory_category_id' => $category->id,
                'name' => $category->name,
            ], $request);

            return $category;
        });
    }
}
