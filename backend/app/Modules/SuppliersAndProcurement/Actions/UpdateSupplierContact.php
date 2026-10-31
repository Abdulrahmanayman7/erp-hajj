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

final class UpdateSupplierContact
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, Supplier $supplier, SupplierContact $contact, array $data, Request $request): SupplierContact
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $supplier, $contact, $data, $request, $tenant): SupplierContact {
            Supplier::query()->whereKey($supplier->id)->lockForUpdate()->firstOrFail();
            SupplierContact::query()
                ->where('supplier_id', $supplier->id)
                ->lockForUpdate()
                ->get();

            $locked = SupplierContact::query()
                ->whereKey($contact->id)
                ->where('supplier_id', $supplier->id)
                ->firstOrFail();

            if (array_key_exists('name', $data)) {
                $locked->name = $data['name'];
            }
            if (array_key_exists('role_title', $data)) {
                $locked->role_title = $data['role_title'];
            }
            if (array_key_exists('email', $data)) {
                $locked->email = $data['email'];
            }
            if (array_key_exists('phone', $data)) {
                $locked->phone = $data['phone'];
            }

            if (array_key_exists('is_primary_contact', $data) && (bool) $data['is_primary_contact']) {
                SupplierContact::query()
                    ->where('supplier_id', $supplier->id)
                    ->whereKeyNot($locked->id)
                    ->update(['is_primary_contact' => false]);
                $locked->is_primary_contact = true;
            } elseif (array_key_exists('is_primary_contact', $data)) {
                $locked->is_primary_contact = false;
            }

            $locked->save();

            $this->security->record(AuthorizationSecurityEvent::SUPPLIER_CONTACT_UPDATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'supplier_id' => $supplier->id,
                'contact_id' => $locked->id,
            ], $request);

            return $locked;
        });
    }
}
