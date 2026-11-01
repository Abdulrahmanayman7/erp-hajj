<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Models\PurchaseApproverAuthority;
use App\Modules\SuppliersAndProcurement\Support\PurchaseApproverAuthorityValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class GrantPurchaseApproverAuthority
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
        private readonly PurchaseApproverAuthorityValidator $validator,
    ) {}

    /**
     * @param  array{
     *   user_id: int,
     *   max_amount: mixed,
     *   covers_unpriced: bool,
     *   effective_from: mixed,
     *   effective_to?: mixed,
     *   organization_unit_id?: mixed,
     *   currency?: string|null
     * }  $data
     */
    public function execute(User $actor, array $data, Request $request): PurchaseApproverAuthority
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $data, $request, $tenant): PurchaseApproverAuthority {
            $subject = $this->validator->resolveSameTenantActiveUser((int) $data['user_id'], (int) $tenant->id);
            $maxAmount = $this->validator->assertMaxAmount($data['max_amount']);
            $from = Carbon::parse((string) $data['effective_from']);
            $to = array_key_exists('effective_to', $data) && $data['effective_to'] !== null && $data['effective_to'] !== ''
                ? Carbon::parse((string) $data['effective_to'])
                : null;
            $this->validator->assertDateRange($from, $to);

            $ouId = array_key_exists('organization_unit_id', $data)
                ? ($data['organization_unit_id'] !== null && $data['organization_unit_id'] !== ''
                    ? (int) $data['organization_unit_id']
                    : null)
                : null;
            $unit = $this->validator->resolveOptionalOrgUnit($ouId);

            $this->validator->assertNoActiveOverlap((int) $subject->id, $from, $to);

            $authority = new PurchaseApproverAuthority([
                'max_amount' => $maxAmount,
                'currency' => isset($data['currency']) && is_string($data['currency']) && $data['currency'] !== ''
                    ? strtoupper($data['currency'])
                    : 'SAR',
                'covers_unpriced' => (bool) $data['covers_unpriced'],
            ]);
            $authority->forceFill([
                'user_id' => $subject->id,
                'organization_unit_id' => $unit?->id,
                'effective_from' => $from,
                'effective_to' => $to,
                'created_by' => $actor->id,
            ]);
            $authority->save();

            $this->security->record(AuthorizationSecurityEvent::AUTHORITY_GRANTED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'purchase_approver_authority',
                'entity_id' => $authority->id,
                'user_id' => $authority->user_id,
                'max_amount' => (string) $authority->max_amount,
                'covers_unpriced' => (bool) $authority->covers_unpriced,
                'organization_unit_id' => $authority->organization_unit_id,
            ], $request);

            return $authority->fresh(['user', 'organizationUnit', 'creator']) ?? $authority;
        });
    }
}
