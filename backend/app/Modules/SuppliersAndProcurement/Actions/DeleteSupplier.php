<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Documents\Enums\DocumentLinkableType;
use App\Modules\Documents\Support\DocumentReferenceValidator;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\Supplier;
use App\Modules\SuppliersAndProcurement\Models\SupplierStatusTransition;
use App\Modules\SuppliersAndProcurement\Support\SupplierReferenceValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class DeleteSupplier
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly SupplierReferenceValidator $references,
        private readonly DocumentReferenceValidator $documents,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, Supplier $supplier, Request $request): void
    {
        $tenant = $this->tenantContext->require();

        DB::transaction(function () use ($actor, $supplier, $request, $tenant): void {
            $locked = Supplier::query()->whereKey($supplier->id)->lockForUpdate()->firstOrFail();
            $this->references->assertDeletableDraft($locked);
            $this->documents->assertNoDocumentsLinked(DocumentLinkableType::Supplier, (int) $locked->id);

            // Eligibility document records are history that must not be orphaned.
            if ($locked->documentRecords()->exists()) {
                throw SupplierDomainException::deleteForbidden();
            }

            $snapshot = [
                'id' => $locked->id,
                'supplier_number' => $locked->supplier_number,
                'display_name' => $locked->display_name,
            ];

            $locked->categories()->detach();
            $locked->coverages()->delete();
            $locked->contacts()->delete();
            SupplierStatusTransition::query()->where('supplier_id', $locked->id)->delete();
            $locked->delete();

            $this->security->record(AuthorizationSecurityEvent::SUPPLIER_DELETED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'supplier' => $snapshot,
            ], $request);
        });
    }
}
