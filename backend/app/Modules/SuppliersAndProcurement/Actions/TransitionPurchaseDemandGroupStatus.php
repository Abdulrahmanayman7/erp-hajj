<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseDemandGroupStatus;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\PurchaseDemandGroup;
use App\Modules\SuppliersAndProcurement\Support\PurchaseDemandGroupEngagementGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class TransitionPurchaseDemandGroupStatus
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
        private readonly PurchaseDemandGroupEngagementGuard $engagement,
    ) {}

    /**
     * @param  array{to: string, reason?: ?string}  $data
     */
    public function execute(
        User $actor,
        PurchaseDemandGroup $group,
        array $data,
        Request $request,
    ): PurchaseDemandGroup {
        $tenant = $this->tenantContext->require();
        $to = PurchaseDemandGroupStatus::tryFrom((string) $data['to']);
        if ($to === null) {
            throw SupplierDomainException::dgInvalidTransition();
        }

        return DB::transaction(function () use ($actor, $group, $to, $data, $request, $tenant): PurchaseDemandGroup {
            $locked = $this->engagement->lockGroup((int) $group->id);
            $from = $locked->status;

            if (! $this->isAllowed($from, $to)) {
                throw SupplierDomainException::dgInvalidTransition();
            }

            if ($from === PurchaseDemandGroupStatus::Active && $to === PurchaseDemandGroupStatus::Draft) {
                if ($this->engagement->hasEngagedActiveMember($locked)) {
                    throw SupplierDomainException::dgEngagementActive();
                }
            }

            if ($from === PurchaseDemandGroupStatus::Frozen && $to === PurchaseDemandGroupStatus::Active) {
                if ($this->engagement->hasEngagedActiveMember($locked)) {
                    throw SupplierDomainException::dgEngagementActive();
                }
            }

            $fill = ['status' => $to];

            if ($to === PurchaseDemandGroupStatus::Frozen) {
                $fill['frozen_at'] = now();
                $fill['frozen_by'] = $actor->id;
                $fill['freeze_reason'] = (string) ($data['reason'] ?? 'manual');
            }

            if ($to === PurchaseDemandGroupStatus::Closed) {
                if ($from !== PurchaseDemandGroupStatus::Frozen) {
                    $fill['frozen_at'] = $locked->frozen_at ?? now();
                    $fill['frozen_by'] = $locked->frozen_by ?? $actor->id;
                    $fill['freeze_reason'] = $locked->freeze_reason ?? 'manual';
                }
                $fill['closed_at'] = now();
                $fill['closed_by'] = $actor->id;
            }

            if ($to === PurchaseDemandGroupStatus::Active || $to === PurchaseDemandGroupStatus::Draft) {
                if ($from === PurchaseDemandGroupStatus::Frozen) {
                    $fill['frozen_at'] = null;
                    $fill['frozen_by'] = null;
                    $fill['freeze_reason'] = null;
                }
            }

            $locked->forceFill($fill)->save();

            $this->security->record(AuthorizationSecurityEvent::DEMAND_GROUP_TRANSITIONED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'purchase_demand_group',
                'entity_id' => $locked->id,
                'group_number' => $locked->group_number,
                'from_status' => $from->value,
                'to_status' => $to->value,
            ], $request);

            return $locked->fresh(['creator', 'season', 'beneficiaryUnit', 'activeMemberships']) ?? $locked;
        });
    }

    private function isAllowed(PurchaseDemandGroupStatus $from, PurchaseDemandGroupStatus $to): bool
    {
        return match ($from) {
            PurchaseDemandGroupStatus::Draft => in_array($to, [
                PurchaseDemandGroupStatus::Active,
                PurchaseDemandGroupStatus::Frozen,
                PurchaseDemandGroupStatus::Closed,
            ], true),
            PurchaseDemandGroupStatus::Active => in_array($to, [
                PurchaseDemandGroupStatus::Draft,
                PurchaseDemandGroupStatus::Frozen,
                PurchaseDemandGroupStatus::Closed,
            ], true),
            PurchaseDemandGroupStatus::Frozen => in_array($to, [
                PurchaseDemandGroupStatus::Active,
                PurchaseDemandGroupStatus::Closed,
            ], true),
            PurchaseDemandGroupStatus::Closed => false,
        };
    }
}
