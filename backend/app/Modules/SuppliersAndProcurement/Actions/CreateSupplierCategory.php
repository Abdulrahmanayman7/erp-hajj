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

final class CreateSupplierCategory
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array{name: string, code?: string|null, is_active?: bool|null}  $data
     */
    public function execute(User $actor, array $data, Request $request): SupplierCategory
    {
        $tenant = $this->tenantContext->require();
        $code = $this->normalizeCode($data['code'] ?? null);

        return DB::transaction(function () use ($actor, $data, $request, $tenant, $code): SupplierCategory {
            if (SupplierCategory::query()->where('name', $data['name'])->exists()) {
                throw SupplierDomainException::categoryNameTaken();
            }

            if ($code !== null && SupplierCategory::query()->where('code', $code)->exists()) {
                throw SupplierDomainException::categoryCodeTaken();
            }

            $category = SupplierCategory::query()->create([
                'name' => $data['name'],
                'code' => $code,
                'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
            ]);

            $this->security->record(AuthorizationSecurityEvent::SUPPLIER_CATEGORY_CREATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'supplier_category_id' => $category->id,
                'name' => $category->name,
            ], $request);

            return $category;
        });
    }

    private function normalizeCode(mixed $code): ?string
    {
        if ($code === null) {
            return null;
        }

        $trimmed = trim((string) $code);

        return $trimmed === '' ? null : $trimmed;
    }
}
