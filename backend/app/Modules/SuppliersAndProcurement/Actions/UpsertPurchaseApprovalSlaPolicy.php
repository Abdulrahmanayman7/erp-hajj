<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestPriority;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\PurchaseApprovalSlaPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use ValueError;

final class UpsertPurchaseApprovalSlaPolicy
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array{
     *   priority: string,
     *   level_order: mixed,
     *   target_hours: mixed,
     *   reminder_offset_hours?: mixed
     * }  $data
     */
    public function execute(User $actor, array $data, Request $request): PurchaseApprovalSlaPolicy
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $data, $request, $tenant): PurchaseApprovalSlaPolicy {
            try {
                $priority = PurchaseRequestPriority::from((string) $data['priority'])->value;
            } catch (ValueError) {
                throw SupplierDomainException::slaPolicyInvalid('أولوية سياسة SLA غير صالحة.');
            }

            $levelOrder = (int) $data['level_order'];
            if ($levelOrder < 1) {
                throw SupplierDomainException::slaPolicyInvalid('ترتيب مستوى SLA يجب أن يكون ≥ 1.');
            }

            $targetHours = (int) $data['target_hours'];
            if ($targetHours < 1) {
                throw SupplierDomainException::slaPolicyInvalid('ساعات هدف SLA يجب أن تكون ≥ 1.');
            }

            $reminder = array_key_exists('reminder_offset_hours', $data) && $data['reminder_offset_hours'] !== null && $data['reminder_offset_hours'] !== ''
                ? (int) $data['reminder_offset_hours']
                : null;
            if ($reminder !== null && $reminder < 0) {
                throw SupplierDomainException::slaPolicyInvalid('إزاحة تذكير SLA غير صالحة.');
            }

            /** @var PurchaseApprovalSlaPolicy|null $existing */
            $existing = PurchaseApprovalSlaPolicy::query()
                ->where('priority', $priority)
                ->where('level_order', $levelOrder)
                ->lockForUpdate()
                ->first();

            if ($existing === null) {
                $policy = new PurchaseApprovalSlaPolicy([
                    'priority' => $priority,
                    'level_order' => $levelOrder,
                    'target_hours' => $targetHours,
                    'reminder_offset_hours' => $reminder,
                ]);
                $policy->save();
            } else {
                $existing->forceFill([
                    'target_hours' => $targetHours,
                    'reminder_offset_hours' => $reminder,
                ])->save();
                $policy = $existing;
            }

            $this->security->record(AuthorizationSecurityEvent::SLA_POLICY_UPSERTED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'purchase_approval_sla_policy',
                'entity_id' => $policy->id,
                'priority' => $policy->priority,
                'level_order' => $policy->level_order,
                'target_hours' => $policy->target_hours,
            ], $request);

            return $policy->fresh() ?? $policy;
        });
    }
}
