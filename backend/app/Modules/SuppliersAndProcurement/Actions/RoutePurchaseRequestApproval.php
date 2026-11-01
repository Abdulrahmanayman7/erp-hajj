<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseApprovalMatrixStatus;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestApprovalInstanceStatus;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestApprovalStepStatus;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestStatus;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestPriority;
use App\Modules\SuppliersAndProcurement\Models\PurchaseApprovalMatrix;
use App\Modules\SuppliersAndProcurement\Models\PurchaseApprovalMatrixRule;
use App\Modules\SuppliersAndProcurement\Models\PurchaseApprovalSlaPolicy;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequest;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequestApprovalInstance;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequestApprovalStep;
use App\Modules\SuppliersAndProcurement\Support\PurchaseRequestApprovalNotifier;
use App\Modules\SuppliersAndProcurement\Support\PurchaseRequestApprovalRuleMatcher;
use App\Modules\SuppliersAndProcurement\Support\PurchaseRequestApproverPoolResolver;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Route a submitted PR onto a published matrix (IV-WP02 / EC-01).
 * Configuration failures persist IV-D6 block and succeed; technical failures throw.
 */
final class RoutePurchaseRequestApproval
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
        private readonly PurchaseRequestApprovalRuleMatcher $ruleMatcher,
        private readonly PurchaseRequestApproverPoolResolver $poolResolver,
        private readonly PurchaseRequestApprovalNotifier $notifier,
    ) {}

    public function execute(
        int $purchaseRequestId,
        int $versionNo,
        ?User $actor = null,
        ?Request $request = null,
    ): PurchaseRequest {
        $tenant = $this->tenantContext->require();

        try {
            return DB::transaction(function () use ($purchaseRequestId, $versionNo, $actor, $request, $tenant): PurchaseRequest {
                /** @var PurchaseRequest $locked */
                $locked = PurchaseRequest::query()->whereKey($purchaseRequestId)->lockForUpdate()->firstOrFail();

                $existing = PurchaseRequestApprovalInstance::query()
                    ->where('purchase_request_id', $locked->id)
                    ->where('version_no', $versionNo)
                    ->first();

                if ($existing !== null) {
                    return $locked->fresh(['lines', 'requester', 'beneficiaryUnit', 'season']) ?? $locked;
                }

                if ($locked->status === PurchaseRequestStatus::UnderReview
                    && (int) $locked->version_no === $versionNo
                    && $locked->matrix_id !== null) {
                    return $locked->fresh(['lines', 'requester', 'beneficiaryUnit', 'season']) ?? $locked;
                }

                if ($locked->status !== PurchaseRequestStatus::Submitted
                    || (int) $locked->version_no !== $versionNo) {
                    return $locked->fresh(['lines', 'requester', 'beneficiaryUnit', 'season']) ?? $locked;
                }

                $matrix = PurchaseApprovalMatrix::query()
                    ->where('status', PurchaseApprovalMatrixStatus::Published)
                    ->orderByDesc('version_no')
                    ->orderByDesc('id')
                    ->first();

                if ($matrix === null) {
                    return $this->block(
                        $locked,
                        'PR_MATRIX_MISSING',
                        'لا توجد مصفوفة اعتماد منشورة لهذه المنشأة.',
                        $actor,
                        $request,
                        $tenant->id,
                    );
                }

                $matrix->load(['rules', 'levels']);

                if ($matrix->levels->isEmpty()) {
                    return $this->block(
                        $locked,
                        'PR_ROUTING_BLOCKED',
                        'مصفوفة الاعتماد المنشورة لا تحتوي على مستويات.',
                        $actor,
                        $request,
                        $tenant->id,
                    );
                }

                try {
                    /** @var PurchaseApprovalMatrixRule $rule */
                    $rule = $this->ruleMatcher->match($locked, $matrix->rules);
                } catch (SupplierDomainException $e) {
                    return $this->block(
                        $locked,
                        $e->errorCode(),
                        $e->getMessage(),
                        $actor,
                        $request,
                        $tenant->id,
                    );
                }

                $pool = $this->poolResolver->eligibleUserIds($locked);
                if ($pool === []) {
                    return $this->block(
                        $locked,
                        'PR_ROUTING_BLOCKED',
                        'لا يوجد معتمد مؤهل لمسار الاعتماد (صلاحية الاعتماد + سلطة مالية، مع استبعاد مقدّم الطلب).',
                        $actor,
                        $request,
                        $tenant->id,
                    );
                }

                $now = now();

                $instance = new PurchaseRequestApprovalInstance;
                $instance->forceFill([
                    'purchase_request_id' => $locked->id,
                    'version_no' => $versionNo,
                    'matrix_id' => $matrix->id,
                    'matrix_version_no' => $matrix->version_no,
                    'rule_id' => $rule->id,
                    'status' => PurchaseRequestApprovalInstanceStatus::Active,
                    'routed_at' => $now,
                ]);
                $instance->save();

                $priority = $locked->priority instanceof PurchaseRequestPriority
                    ? $locked->priority->value
                    : (string) $locked->priority;

                $slaByLevel = PurchaseApprovalSlaPolicy::query()
                    ->where('priority', $priority)
                    ->get()
                    ->keyBy(fn (PurchaseApprovalSlaPolicy $p): int => (int) $p->level_order);

                foreach ($matrix->levels->sortBy('level_order') as $level) {
                    $levelOrder = (int) $level->level_order;
                    $dueAt = null;
                    /** @var PurchaseApprovalSlaPolicy|null $sla */
                    $sla = $slaByLevel->get($levelOrder);
                    if ($sla !== null && (int) $sla->target_hours > 0) {
                        $dueAt = $now->copy()->addHours((int) $sla->target_hours);
                    }

                    $step = new PurchaseRequestApprovalStep;
                    $step->forceFill([
                        'instance_id' => $instance->id,
                        'level_order' => $levelOrder,
                        'status' => PurchaseRequestApprovalStepStatus::Pending,
                        'due_at' => $dueAt,
                    ]);
                    $step->save();
                }

                $locked->forceFill([
                    'status' => PurchaseRequestStatus::UnderReview,
                    'matrix_id' => $matrix->id,
                    'matrix_version_no' => $matrix->version_no,
                    'routing_blocked_at' => null,
                    'routing_block_code' => null,
                    'routing_block_message' => null,
                ])->save();

                $this->security->record(AuthorizationSecurityEvent::PR_ROUTED, [
                    'tenant_id' => $tenant->id,
                    'actor_id' => $actor?->id,
                    'entity_type' => 'purchase_request',
                    'entity_id' => $locked->id,
                    'pr_number' => $locked->pr_number,
                    'version_no' => $versionNo,
                    'matrix_id' => $matrix->id,
                    'matrix_version_no' => $matrix->version_no,
                    'rule_id' => $rule->id,
                    'instance_id' => $instance->id,
                    'status' => PurchaseRequestStatus::UnderReview->value,
                    'pool_size' => count($pool),
                ], $request);

                $firstLevel = (int) $matrix->levels->sortBy('level_order')->first()?->level_order;
                $this->notifier->notifyPoolReady($locked, $pool, $firstLevel > 0 ? $firstLevel : 1);

                return $locked->fresh(['lines', 'requester', 'beneficiaryUnit', 'season', 'approvalInstances']) ?? $locked;
            });
        } catch (UniqueConstraintViolationException) {
            /** @var PurchaseRequest $pr */
            $pr = PurchaseRequest::query()->whereKey($purchaseRequestId)->firstOrFail();

            return $pr->fresh(['lines', 'requester', 'beneficiaryUnit', 'season']) ?? $pr;
        }
    }

    private function block(
        PurchaseRequest $pr,
        string $code,
        string $message,
        ?User $actor,
        ?Request $request,
        int $tenantId,
    ): PurchaseRequest {
        $pr->forceFill([
            'routing_blocked_at' => now(),
            'routing_block_code' => $code,
            'routing_block_message' => $message,
        ])->save();

        $this->security->record(AuthorizationSecurityEvent::PR_ROUTING_BLOCKED, [
            'tenant_id' => $tenantId,
            'actor_id' => $actor?->id,
            'entity_type' => 'purchase_request',
            'entity_id' => $pr->id,
            'pr_number' => $pr->pr_number,
            'version_no' => $pr->version_no,
            'routing_block_code' => $code,
            'status' => $pr->status instanceof PurchaseRequestStatus
                ? $pr->status->value
                : (string) $pr->status,
        ], $request);

        $this->notifier->notifyAdminsRoutingBlocked($pr, $code, $request);

        return $pr->fresh(['lines', 'requester', 'beneficiaryUnit', 'season']) ?? $pr;
    }
}
