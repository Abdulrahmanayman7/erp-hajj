<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\PurchaseApprovalDelegation;
use App\Modules\SuppliersAndProcurement\Support\PurchaseApprovalDelegationValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class CreatePurchaseApprovalDelegation
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
        private readonly PurchaseApprovalDelegationValidator $validator,
    ) {}

    /**
     * @param  array{
     *   from_user_id?: mixed,
     *   to_user_id: mixed,
     *   max_amount: mixed,
     *   starts_at: mixed,
     *   ends_at: mixed,
     *   currency?: string|null
     * }  $data
     */
    public function execute(User $actor, array $data, Request $request): PurchaseApprovalDelegation
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $data, $request, $tenant): PurchaseApprovalDelegation {
            $isAdmin = $actor->hasPermission('purchase_requests.manage_delegations');
            $canSelf = $actor->hasPermission('purchase_requests.approve');

            $toUserId = (int) $data['to_user_id'];
            $fromUserId = array_key_exists('from_user_id', $data) && $data['from_user_id'] !== null && $data['from_user_id'] !== ''
                ? (int) $data['from_user_id']
                : (int) $actor->id;

            if (! $isAdmin) {
                if (! $canSelf || $fromUserId !== (int) $actor->id) {
                    throw SupplierDomainException::prDelegationInvalid(
                        'إنشاء التفويض الذاتي يتطلب صلاحية الاعتماد وأن يكون المفوِّض هو المستخدم الحالي.',
                    );
                }
            }

            $this->validator->assertFromNotTo($fromUserId, $toUserId);

            $fromUser = $this->validator->resolveSameTenantActiveUser($fromUserId, (int) $tenant->id);
            $toUser = $this->validator->resolveSameTenantActiveUser($toUserId, (int) $tenant->id);

            $startsAt = Carbon::parse((string) $data['starts_at']);
            $endsAt = Carbon::parse((string) $data['ends_at']);
            $this->validator->assertDateRange($startsAt, $endsAt);

            $requestedMax = $this->validator->assertMaxAmount($data['max_amount']);

            $authorities = $this->validator->activeDirectAuthorities($fromUser->id, $startsAt);
            if ($authorities->isEmpty()) {
                throw SupplierDomainException::prDelegationInvalid(
                    'المفوِّض يجب أن يملك سلطة اعتماد مباشرة نشطة (عمق واحد فقط — لا إعادة تفويض).',
                );
            }

            $directCeiling = $this->validator->maxDirectCeiling($authorities);
            if ($directCeiling === null) {
                throw SupplierDomainException::prDelegationInvalid(
                    'المفوِّض يجب أن يملك سلطة اعتماد مباشرة نشطة.',
                );
            }

            // Effective ceiling = min(delegator direct, requested); store clamped amount.
            $storedMax = bccomp($requestedMax, $directCeiling, 2) > 0
                ? $directCeiling
                : $requestedMax;

            $this->validator->assertNoActiveOverlap($fromUser->id, $toUser->id, $startsAt, $endsAt);

            $delegation = new PurchaseApprovalDelegation([
                'max_amount' => $storedMax,
                'currency' => isset($data['currency']) && is_string($data['currency']) && $data['currency'] !== ''
                    ? strtoupper($data['currency'])
                    : 'SAR',
            ]);
            $delegation->forceFill([
                'from_user_id' => $fromUser->id,
                'to_user_id' => $toUser->id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'authorized_by' => $actor->id,
            ]);
            $delegation->save();

            $this->security->record(AuthorizationSecurityEvent::DELEGATION_CREATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'purchase_approval_delegation',
                'entity_id' => $delegation->id,
                'from_user_id' => $delegation->from_user_id,
                'to_user_id' => $delegation->to_user_id,
                'max_amount' => (string) $delegation->max_amount,
                'starts_at' => $delegation->starts_at?->toIso8601String(),
                'ends_at' => $delegation->ends_at?->toIso8601String(),
            ], $request);

            return $delegation->fresh(['fromUser', 'toUser', 'authorizer']) ?? $delegation;
        });
    }
}
