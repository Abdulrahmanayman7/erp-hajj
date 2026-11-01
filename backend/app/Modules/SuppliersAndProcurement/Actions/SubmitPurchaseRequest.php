<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Shared\CorrelationId;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestStatus;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Jobs\RoutePurchaseRequestApprovalJob;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequest;
use App\Modules\SuppliersAndProcurement\Support\FindPurchaseRequestDuplicateHints;
use App\Modules\SuppliersAndProcurement\Support\PurchaseRequestPreconditions;
use App\Modules\SuppliersAndProcurement\Support\PurchaseRequestTotals;
use App\Modules\SuppliersAndProcurement\Support\PurchaseRequestVersionSnapshot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class SubmitPurchaseRequest
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
        private readonly PurchaseRequestPreconditions $preconditions,
        private readonly PurchaseRequestTotals $totals,
        private readonly PurchaseRequestVersionSnapshot $snapshots,
        private readonly FindPurchaseRequestDuplicateHints $duplicates,
        private readonly CorrelationId $correlationId,
    ) {}

    /**
     * @param  array{acknowledge_duplicate_warning?: bool}  $data
     */
    public function execute(User $actor, PurchaseRequest $pr, array $data, Request $request): PurchaseRequest
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $pr, $data, $request, $tenant): PurchaseRequest {
            /** @var PurchaseRequest $locked */
            $locked = PurchaseRequest::query()->whereKey($pr->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isEditable()) {
                throw SupplierDomainException::prInvalidTransition();
            }

            $locked->load('lines');
            $lineRows = $locked->lines->map(fn ($l) => [
                'unit_estimate' => $l->unit_estimate,
                'line_estimate' => $l->line_estimate,
                'unpriced_reason' => $l->unpriced_reason,
            ])->all();
            $this->preconditions->assertLinesForSubmit($lineRows);

            $this->preconditions->resolveSeason($locked->request_kind, $locked->season_id);
            $this->preconditions->resolveOrgUnit((int) $locked->beneficiary_organization_unit_id);
            $this->preconditions->resolveOptionalSupplier(
                $locked->optional_supplier_id !== null ? (int) $locked->optional_supplier_id : null,
            );
            $this->preconditions->assertPriority($locked->priority, $locked->urgency_justification);

            $hints = $this->duplicates->forPurchaseRequest($locked);
            $ack = (bool) ($data['acknowledge_duplicate_warning'] ?? false);
            if ($hints !== [] && ! $ack && $locked->duplicate_warning_acknowledged_at === null) {
                throw SupplierDomainException::prDuplicateAckRequired();
            }

            if ($ack) {
                $locked->forceFill(['duplicate_warning_acknowledged_at' => now()])->save();
                $this->security->record(AuthorizationSecurityEvent::PR_DUPLICATE_ACKNOWLEDGED, [
                    'tenant_id' => $tenant->id,
                    'actor_id' => $actor->id,
                    'entity_type' => 'purchase_request',
                    'entity_id' => $locked->id,
                ], $request);
            }

            // First submit keeps version_no=1; resubmit from returned increments.
            if ($locked->status === PurchaseRequestStatus::Returned) {
                $locked->forceFill(['version_no' => (int) $locked->version_no + 1])->save();
            }

            $this->totals->recompute($locked->fresh(['lines']) ?? $locked);
            $locked = $locked->fresh(['lines']) ?? $locked;

            $locked->forceFill([
                'status' => PurchaseRequestStatus::Submitted,
                'submitted_at' => now(),
                'matrix_id' => null,
                'matrix_version_no' => null,
                'routing_blocked_at' => null,
                'routing_block_code' => null,
                'routing_block_message' => null,
            ])->save();

            $this->snapshots->capture($locked, $actor);

            $this->security->record(AuthorizationSecurityEvent::PR_SUBMITTED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'purchase_request',
                'entity_id' => $locked->id,
                'pr_number' => $locked->pr_number,
                'version_no' => $locked->version_no,
                'status' => PurchaseRequestStatus::Submitted->value,
            ], $request);

            // Capture trusted routing payload before afterCommit (never from client).
            $routeTenantId = (int) $tenant->id;
            $routePrId = (int) $locked->id;
            $routeVersionNo = (int) $locked->version_no;
            $routeCorrelation = $this->correlationId->get();

            DB::afterCommit(function () use ($routeTenantId, $routePrId, $routeVersionNo, $routeCorrelation): void {
                // TenantContext remains set for this request; job captures trusted tenant_id.
                $job = new RoutePurchaseRequestApprovalJob(
                    $routePrId,
                    $routeVersionNo,
                    $routeCorrelation,
                );

                if ((int) $job->tenantId !== $routeTenantId) {
                    throw new \LogicException('Route job tenant_id mismatch after submit commit.');
                }

                dispatch($job);
            });

            return $locked->fresh(['lines', 'requester', 'beneficiaryUnit', 'optionalSupplier', 'season', 'versions']) ?? $locked;
        });
    }
}
