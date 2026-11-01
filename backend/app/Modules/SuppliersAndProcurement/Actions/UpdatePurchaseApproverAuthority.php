<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\PurchaseApproverAuthority;
use App\Modules\SuppliersAndProcurement\Support\PurchaseApproverAuthorityValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class UpdatePurchaseApproverAuthority
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
        private readonly PurchaseApproverAuthorityValidator $validator,
    ) {}

    /**
     * @param  array{
     *   max_amount?: mixed,
     *   covers_unpriced?: bool,
     *   effective_from?: mixed,
     *   effective_to?: mixed,
     *   organization_unit_id?: mixed,
     *   currency?: string|null
     * }  $data
     */
    public function execute(
        User $actor,
        PurchaseApproverAuthority $authority,
        array $data,
        Request $request,
    ): PurchaseApproverAuthority {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $authority, $data, $request, $tenant): PurchaseApproverAuthority {
            /** @var PurchaseApproverAuthority $locked */
            $locked = PurchaseApproverAuthority::query()->whereKey($authority->id)->lockForUpdate()->firstOrFail();

            if ($locked->isRevoked()) {
                throw SupplierDomainException::authorityAlreadyRevoked();
            }

            $maxAmount = array_key_exists('max_amount', $data)
                ? $this->validator->assertMaxAmount($data['max_amount'])
                : (string) $locked->max_amount;

            $coversUnpriced = array_key_exists('covers_unpriced', $data)
                ? (bool) $data['covers_unpriced']
                : (bool) $locked->covers_unpriced;

            $from = array_key_exists('effective_from', $data) && $data['effective_from'] !== null
                ? Carbon::parse((string) $data['effective_from'])
                : $locked->effective_from;

            $to = $locked->effective_to;
            if (array_key_exists('effective_to', $data)) {
                $to = $data['effective_to'] !== null && $data['effective_to'] !== ''
                    ? Carbon::parse((string) $data['effective_to'])
                    : null;
            }

            $this->validator->assertDateRange($from, $to);

            $ouId = $locked->organization_unit_id;
            if (array_key_exists('organization_unit_id', $data)) {
                $ouId = $data['organization_unit_id'] !== null && $data['organization_unit_id'] !== ''
                    ? (int) $data['organization_unit_id']
                    : null;
            }
            $unit = $this->validator->resolveOptionalOrgUnit($ouId !== null ? (int) $ouId : null);

            $this->validator->assertNoActiveOverlap((int) $locked->user_id, $from, $to, (int) $locked->id);

            $fill = [
                'max_amount' => $maxAmount,
                'covers_unpriced' => $coversUnpriced,
            ];
            if (array_key_exists('currency', $data) && is_string($data['currency']) && $data['currency'] !== '') {
                $fill['currency'] = strtoupper($data['currency']);
            }

            $locked->fill($fill);
            $locked->forceFill([
                'organization_unit_id' => $unit?->id,
                'effective_from' => $from,
                'effective_to' => $to,
            ]);
            $locked->save();

            $this->security->record(AuthorizationSecurityEvent::AUTHORITY_UPDATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'purchase_approver_authority',
                'entity_id' => $locked->id,
                'user_id' => $locked->user_id,
                'max_amount' => (string) $locked->max_amount,
                'covers_unpriced' => (bool) $locked->covers_unpriced,
                'organization_unit_id' => $locked->organization_unit_id,
            ], $request);

            return $locked->fresh(['user', 'organizationUnit', 'creator']) ?? $locked;
        });
    }
}
