<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Enums\SupplierStatus;
use App\Modules\SuppliersAndProcurement\Models\Supplier;
use App\Modules\SuppliersAndProcurement\Support\SupplierLifecycle;
use App\Modules\SuppliersAndProcurement\Support\SupplierTransitionRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class TransitionSupplier
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly SupplierTransitionRecorder $transitions,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, Supplier $supplier, SupplierStatus $to, ?string $reason, Request $request): Supplier
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $supplier, $to, $reason, $request, $tenant): Supplier {
            $locked = Supplier::query()->whereKey($supplier->id)->lockForUpdate()->firstOrFail();
            $from = $locked->status;

            SupplierLifecycle::assertAllowed($from, $to);

            $locked->status = $to;
            if ($to === SupplierStatus::Active) {
                $locked->activated_at = now();
            }
            $locked->save();

            $this->transitions->record($locked, $from, $to, $actor, $reason, $request);

            $this->security->record(AuthorizationSecurityEvent::SUPPLIER_TRANSITIONED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'supplier_id' => $locked->id,
                'from_status' => $from->value,
                'to_status' => $to->value,
            ], $request);

            return $locked->load(['categories', 'createdBy', 'statusTransitions.actor']);
        });
    }
}
