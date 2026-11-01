<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Enums\SupplierStatus;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\Supplier;
use App\Modules\SuppliersAndProcurement\Support\EligibilityService;
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
        private readonly EligibilityService $eligibility,
    ) {}

    public function execute(User $actor, Supplier $supplier, SupplierStatus $to, ?string $reason, Request $request): Supplier
    {
        $tenant = $this->tenantContext->require();

        try {
            return DB::transaction(function () use ($actor, $supplier, $to, $reason, $request, $tenant): Supplier {
                $locked = Supplier::query()->whereKey($supplier->id)->lockForUpdate()->firstOrFail();
                $from = $locked->status;

                SupplierLifecycle::assertAllowed($from, $to);

                $eligibilitySnapshot = [];
                if ($to === SupplierStatus::Active) {
                    $evaluation = $this->eligibility->assertActivatable($locked);
                    $eligibilitySnapshot = [
                        'eligibility_config_ids' => array_column($evaluation['configs'], 'id'),
                        'eligibility_requirement_keys' => $evaluation['applicable_requirement_keys'],
                        'eligibility_document_record_ids' => $evaluation['satisfying_record_ids'],
                    ];
                }

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
                    ...$eligibilitySnapshot,
                ], $request);

                return $locked->load(['categories', 'createdBy', 'statusTransitions.actor']);
            });
        } catch (SupplierDomainException $e) {
            // Audited after rollback so the failure record is not lost with the transaction.
            if ($to === SupplierStatus::Active && $e->errorCode() === 'SUPPLIER_NOT_ELIGIBLE') {
                $this->security->record(AuthorizationSecurityEvent::SUPPLIER_ELIGIBILITY_CHECK_FAILED, [
                    'tenant_id' => $tenant->id,
                    'actor_id' => $actor->id,
                    'supplier_id' => $supplier->id,
                    'entity_type' => 'supplier',
                    'entity_id' => $supplier->id,
                    'attempted_status' => $to->value,
                    'reason_codes' => array_column($e->reasons(), 'code'),
                ], $request);
            }

            throw $e;
        }
    }
}
