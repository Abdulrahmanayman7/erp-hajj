<?php

namespace App\Modules\SuppliersAndProcurement\Support;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Modules\Notifications\Enums\NotificationType;
use App\Modules\Notifications\Support\NotificationDispatcher;
use App\Modules\Notifications\Support\NotificationRecipientResolver;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequest;
use Illuminate\Http\Request;

/**
 * Operational PR approval notifications (IV-D14 / EC-04 / ADR-0013).
 * Dispatcher already uses DB::afterCommit + dedupe keys.
 */
final class PurchaseRequestApprovalNotifier
{
    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
        private readonly NotificationRecipientResolver $recipients,
        private readonly AuthorizationSecurity $security,
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * @param  list<int>  $poolUserIds
     */
    public function notifyPoolReady(
        PurchaseRequest $pr,
        array $poolUserIds,
        int $levelOrder,
        ?string $occurrenceKey = null,
    ): void {
        $this->dispatcher->notify(
            type: NotificationType::PurchaseApprovalPoolReady,
            recipientUsers: $poolUserIds,
            entityType: 'purchase_request',
            entityId: (int) $pr->id,
            dedupeBucket: 'pool-ready',
            occurrenceKey: $occurrenceKey ?? sprintf(
                'pr-pool-ready:%d:v%d:L%d',
                (int) $pr->id,
                (int) $pr->version_no,
                $levelOrder,
            ),
            context: [
                'number' => $pr->pr_number,
                'level_order' => $levelOrder,
            ],
        );
    }

    public function notifyRequesterLifecycle(
        PurchaseRequest $pr,
        NotificationType $type,
        string $occurrenceSuffix,
    ): void {
        $requesterId = (int) $pr->requested_by;
        if ($requesterId <= 0) {
            return;
        }

        $this->dispatcher->notify(
            type: $type,
            recipientUsers: [$requesterId],
            entityType: 'purchase_request',
            entityId: (int) $pr->id,
            dedupeBucket: $occurrenceSuffix,
            occurrenceKey: sprintf(
                'pr-lifecycle:%s:%d:v%d',
                $occurrenceSuffix,
                (int) $pr->id,
                (int) $pr->version_no,
            ),
            context: [
                'number' => $pr->pr_number,
            ],
        );
    }

    public function notifyAdminsRoutingBlocked(
        PurchaseRequest $pr,
        string $blockCode,
        ?Request $request = null,
    ): void {
        $admins = $this->recipients->usersWithPermission('purchase_requests.manage_approval_config');
        if ($admins === []) {
            $this->recordEmptyAdmins($pr, 'routing_blocked', $request);

            return;
        }

        $this->dispatcher->notify(
            type: NotificationType::PurchaseApprovalRoutingBlocked,
            recipientUsers: $admins,
            entityType: 'purchase_request',
            entityId: (int) $pr->id,
            dedupeBucket: 'routing-blocked',
            occurrenceKey: sprintf(
                'pr-routing-blocked:%d:v%d:%s',
                (int) $pr->id,
                (int) $pr->version_no,
                $blockCode,
            ),
            context: [
                'number' => $pr->pr_number,
                'routing_block_code' => $blockCode,
            ],
        );
    }

    public function notifyAdminsNoEligibleFinal(
        PurchaseRequest $pr,
        ?Request $request = null,
    ): void {
        $admins = $this->recipients->usersWithPermission('purchase_requests.manage_approval_config');
        if ($admins === []) {
            $this->recordEmptyAdmins($pr, 'no_eligible_final', $request);

            return;
        }

        $this->dispatcher->notify(
            type: NotificationType::PurchaseApprovalNoEligibleFinal,
            recipientUsers: $admins,
            entityType: 'purchase_request',
            entityId: (int) $pr->id,
            dedupeBucket: 'no-eligible-final',
            occurrenceKey: sprintf(
                'pr-no-eligible-final:%d:v%d',
                (int) $pr->id,
                (int) $pr->version_no,
            ),
            context: [
                'number' => $pr->pr_number,
            ],
        );
    }

    private function recordEmptyAdmins(
        PurchaseRequest $pr,
        string $reason,
        ?Request $request,
    ): void {
        $tenant = $this->tenantContext->require();
        $this->security->record(AuthorizationSecurityEvent::PR_ADMIN_RECIPIENTS_EMPTY, [
            'tenant_id' => $tenant->id,
            'actor_id' => null,
            'entity_type' => 'purchase_request',
            'entity_id' => $pr->id,
            'pr_number' => $pr->pr_number,
            'version_no' => $pr->version_no,
            'reason' => $reason,
            'routing_block_code' => $pr->routing_block_code,
        ], $request);
    }
}
