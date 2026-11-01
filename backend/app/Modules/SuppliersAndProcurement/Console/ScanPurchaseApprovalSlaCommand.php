<?php

namespace App\Modules\SuppliersAndProcurement\Console;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantContext;
use App\Core\Tenancy\TenantStatus;
use App\Modules\Notifications\Enums\NotificationType;
use App\Modules\Notifications\Support\NotificationDispatcher;
use App\Modules\Notifications\Support\NotificationRecipientResolver;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestApprovalInstanceStatus;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestApprovalStepStatus;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequest;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequestApprovalStep;
use App\Modules\SuppliersAndProcurement\Support\PurchaseRequestApproverPoolResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Escalate overdue pending approval steps — notifications only; never auto-approve (IV-D12).
 */
class ScanPurchaseApprovalSlaCommand extends Command
{
    protected $signature = 'purchase-requests:scan-approval-sla';

    protected $description = 'Mark overdue PR approval steps escalated and notify pool + approval admins (no auto-approve)';

    public function handle(
        TenantContext $tenantContext,
        NotificationDispatcher $dispatcher,
        NotificationRecipientResolver $recipients,
        PurchaseRequestApproverPoolResolver $poolResolver,
        AuthorizationSecurity $security,
    ): int {
        $escalated = 0;

        Tenant::query()
            ->where('status', TenantStatus::Active)
            ->orderBy('id')
            ->chunkById(50, function ($tenants) use (
                $tenantContext,
                $dispatcher,
                $recipients,
                $poolResolver,
                $security,
                &$escalated,
            ): void {
                foreach ($tenants as $tenant) {
                    /** @var Tenant $tenant */
                    $tenantContext->runAsTenant($tenant, function () use (
                        $dispatcher,
                        $recipients,
                        $poolResolver,
                        $security,
                        $tenant,
                        &$escalated,
                    ): void {
                        $now = now();
                        $bucket = $now->toDateString();

                        PurchaseRequestApprovalStep::query()
                            ->where('status', PurchaseRequestApprovalStepStatus::Pending)
                            ->whereNotNull('due_at')
                            ->where('due_at', '<', $now)
                            ->whereNull('escalated_at')
                            ->orderBy('id')
                            ->chunkById(100, function ($steps) use (
                                $dispatcher,
                                $recipients,
                                $poolResolver,
                                $security,
                                $tenant,
                                $bucket,
                                $now,
                                &$escalated,
                            ): void {
                                foreach ($steps as $step) {
                                    /** @var PurchaseRequestApprovalStep $step */
                                    $did = DB::transaction(function () use (
                                        $step,
                                        $dispatcher,
                                        $recipients,
                                        $poolResolver,
                                        $security,
                                        $tenant,
                                        $bucket,
                                        $now,
                                    ): bool {
                                        /** @var PurchaseRequestApprovalStep|null $locked */
                                        $locked = PurchaseRequestApprovalStep::query()
                                            ->whereKey($step->id)
                                            ->lockForUpdate()
                                            ->first();

                                        if ($locked === null
                                            || $locked->status !== PurchaseRequestApprovalStepStatus::Pending
                                            || $locked->escalated_at !== null
                                            || $locked->due_at === null
                                            || $locked->due_at->gte($now)) {
                                            return false;
                                        }

                                        $locked->forceFill(['escalated_at' => $now])->save();

                                        $instance = $locked->instance;
                                        if ($instance === null
                                            || $instance->status !== PurchaseRequestApprovalInstanceStatus::Active) {
                                            return true;
                                        }

                                        /** @var PurchaseRequest|null $pr */
                                        $pr = $instance->purchaseRequest;
                                        if ($pr === null) {
                                            return true;
                                        }

                                        $maxLevel = (int) $instance->steps()->max('level_order');
                                        $isFinal = (int) $locked->level_order === $maxLevel;
                                        $poolIds = $poolResolver->eligibleUserIds($pr, forFinalLevel: $isFinal);
                                        $admins = $recipients->usersWithPermission('purchase_requests.manage_approval_config');

                                        $notifyUsers = array_merge($poolIds, $admins);

                                        $dispatcher->notify(
                                            type: NotificationType::PurchaseApprovalSlaEscalated,
                                            recipientUsers: $notifyUsers,
                                            entityType: 'purchase_request',
                                            entityId: (int) $pr->id,
                                            dedupeBucket: $bucket,
                                            occurrenceKey: "pr-sla-escalate:{$locked->id}:{$bucket}",
                                            context: [
                                                'number' => $pr->pr_number,
                                                'level_order' => (int) $locked->level_order,
                                            ],
                                        );

                                        $security->record(AuthorizationSecurityEvent::PR_SLA_ESCALATED, [
                                            'tenant_id' => $tenant->id,
                                            'actor_id' => null,
                                            'entity_type' => 'purchase_request',
                                            'entity_id' => $pr->id,
                                            'pr_number' => $pr->pr_number,
                                            'step_id' => $locked->id,
                                            'level_order' => $locked->level_order,
                                            'due_at' => $locked->due_at?->toIso8601String(),
                                            'status' => $pr->status instanceof \BackedEnum
                                                ? $pr->status->value
                                                : (string) $pr->status,
                                        ], null);

                                        return true;
                                    });

                                    if ($did) {
                                        $escalated++;
                                    }
                                }
                            });
                    });
                }
            });

        $this->info("Scanned purchase approval SLA; escalated={$escalated}.");

        return self::SUCCESS;
    }
}
