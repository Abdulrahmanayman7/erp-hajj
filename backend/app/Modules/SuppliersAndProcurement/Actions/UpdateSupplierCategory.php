<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\SupplierCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class UpdateSupplierCategory
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, SupplierCategory $category, array $data, Request $request): SupplierCategory
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $category, $data, $request, $tenant): SupplierCategory {
            $locked = SupplierCategory::query()->whereKey($category->id)->lockForUpdate()->firstOrFail();

            if (array_key_exists('code', $data) && $data['code'] !== $locked->code) {
                throw SupplierDomainException::categoryCodeImmutable();
            }

            if (array_key_exists('name', $data)) {
                $name = $data['name'];
                if (SupplierCategory::query()->where('name', $name)->whereKeyNot($locked->id)->exists()) {
                    throw SupplierDomainException::categoryNameTaken();
                }
                $locked->name = $name;
            }

            if (array_key_exists('is_active', $data)) {
                $locked->is_active = (bool) $data['is_active'];
            }

            $locked->save();

            $this->security->record(AuthorizationSecurityEvent::SUPPLIER_CATEGORY_UPDATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'supplier_category_id' => $locked->id,
            ], $request);

            return $locked;
        });
    }
}
