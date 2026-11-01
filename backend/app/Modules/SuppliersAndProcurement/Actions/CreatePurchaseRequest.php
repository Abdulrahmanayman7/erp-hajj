<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestKind;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestPriority;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestStatus;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequest;
use App\Modules\SuppliersAndProcurement\Support\PurchaseRequestNumberGenerator;
use App\Modules\SuppliersAndProcurement\Support\PurchaseRequestPreconditions;
use App\Modules\SuppliersAndProcurement\Support\PurchaseRequestTotals;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CreatePurchaseRequest
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
        private readonly PurchaseRequestNumberGenerator $numbers,
        private readonly PurchaseRequestPreconditions $preconditions,
        private readonly PurchaseRequestTotals $totals,
        private readonly ReplacePurchaseRequestLines $replaceLines,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, array $data, Request $request): PurchaseRequest
    {
        $tenant = $this->tenantContext->require();
        $kind = PurchaseRequestKind::from((string) $data['request_kind']);
        $priority = PurchaseRequestPriority::from((string) ($data['priority'] ?? 'normal'));

        // Bounded InnoDB deadlock / lock-wait retries (Laravel). Numbering
        // correctness under contention is the sequence row lock; attempts only
        // recover transient engine contention during the create unit of work.
        return DB::transaction(function () use ($actor, $data, $request, $tenant, $kind, $priority): PurchaseRequest {
            $season = $this->preconditions->resolveSeason(
                $kind,
                isset($data['season_id']) ? (int) $data['season_id'] : null,
            );
            $unit = $this->preconditions->resolveOrgUnit((int) $data['beneficiary_organization_unit_id']);
            $supplier = $this->preconditions->resolveOptionalSupplier(
                isset($data['optional_supplier_id']) ? (int) $data['optional_supplier_id'] : null,
            );
            $this->preconditions->assertPriority($priority, $data['urgency_justification'] ?? null);

            $pr = new PurchaseRequest([
                'title' => (string) $data['title'],
                'purpose' => (string) $data['purpose'],
                'urgency_justification' => $data['urgency_justification'] ?? null,
            ]);
            $pr->forceFill([
                'pr_number' => $this->numbers->next(),
                'version_no' => 1,
                'status' => PurchaseRequestStatus::Draft,
                'request_kind' => $kind,
                'season_id' => $season?->id,
                'priority' => $priority,
                'requested_by' => $actor->id,
                'beneficiary_organization_unit_id' => $unit->id,
                'optional_supplier_id' => $supplier?->id,
                'currency' => (string) ($data['currency'] ?? 'SAR'),
                'matrix_id' => null,
                'matrix_version_no' => null,
            ]);
            $pr->save();

            if (isset($data['lines']) && is_array($data['lines'])) {
                $this->replaceLines->execute($actor, $pr, $data['lines'], $request, audit: false);
            }

            $this->totals->recompute($pr->fresh() ?? $pr);

            $this->security->record(AuthorizationSecurityEvent::PR_CREATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'purchase_request',
                'entity_id' => $pr->id,
                'pr_number' => $pr->pr_number,
                'status' => $pr->status->value,
            ], $request);

            return $pr->fresh(['lines', 'requester', 'beneficiaryUnit', 'optionalSupplier', 'season']) ?? $pr;
        }, 5);
    }
}
