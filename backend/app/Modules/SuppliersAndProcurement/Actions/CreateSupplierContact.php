<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Models\Supplier;
use App\Modules\SuppliersAndProcurement\Models\SupplierContact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CreateSupplierContact
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, Supplier $supplier, array $data, Request $request): SupplierContact
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $supplier, $data, $request, $tenant): SupplierContact {
            // Row lock serializes primary-contact changes for this supplier.
            Supplier::query()->whereKey($supplier->id)->lockForUpdate()->firstOrFail();
            SupplierContact::query()
                ->where('supplier_id', $supplier->id)
                ->lockForUpdate()
                ->get();

            $isPrimary = (bool) ($data['is_primary_contact'] ?? false);

            if ($isPrimary) {
                SupplierContact::query()
                    ->where('supplier_id', $supplier->id)
                    ->update(['is_primary_contact' => false]);
            }

            $contact = SupplierContact::query()->create([
                'supplier_id' => $supplier->id,
                'name' => $data['name'],
                'role_title' => $data['role_title'] ?? null,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'is_primary_contact' => $isPrimary,
            ]);

            $this->security->record(AuthorizationSecurityEvent::SUPPLIER_CONTACT_CREATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'supplier_id' => $supplier->id,
                'contact_id' => $contact->id,
            ], $request);

            return $contact;
        });
    }
}
