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

final class UpdateInventoryCategory
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, InventoryCategory $category, array $data, Request $request): InventoryCategory
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $category, $data, $request, $tenant): InventoryCategory {
            $locked = InventoryCategory::query()->whereKey($category->id)->lockForUpdate()->firstOrFail();

            if (array_key_exists('name', $data)) {
                $name = trim((string) $data['name']);
                $taken = InventoryCategory::query()
                    ->where('name', $name)
                    ->whereKeyNot($locked->id)
                    ->exists();
                if ($taken) {
                    throw InventoryDomainException::categoryNameTaken();
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

            $this->security->record(AuthorizationSecurityEvent::INVENTORY_CATEGORY_UPDATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'inventory_category_id' => $locked->id,
                'name' => $locked->name,
            ], $request);

            return $locked;
        });
    }
}
