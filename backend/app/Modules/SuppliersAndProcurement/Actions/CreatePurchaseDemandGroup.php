<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseDemandGroupStatus;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\PurchaseDemandGroup;
use App\Modules\SuppliersAndProcurement\Support\PurchaseDemandGroupNumberGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CreatePurchaseDemandGroup
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
        private readonly PurchaseDemandGroupNumberGenerator $numbers,
    ) {}

    /**
     * @param  array{title: string, notes?: ?string, season_id?: ?int, beneficiary_organization_unit_id?: ?int}  $data
     */
    public function execute(User $actor, array $data, Request $request): PurchaseDemandGroup
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $data, $request, $tenant): PurchaseDemandGroup {
            $seasonId = isset($data['season_id']) ? (int) $data['season_id'] : null;
            $unitId = isset($data['beneficiary_organization_unit_id'])
                ? (int) $data['beneficiary_organization_unit_id']
                : null;

            if ($seasonId !== null && $seasonId > 0) {
                if (Season::query()->whereKey($seasonId)->doesntExist()) {
                    throw SupplierDomainException::prSeasonInvalid();
                }
            } else {
                $seasonId = null;
            }

            if ($unitId !== null && $unitId > 0) {
                if (OrganizationUnit::query()->whereKey($unitId)->doesntExist()) {
                    throw SupplierDomainException::prOrgUnitNotFound();
                }
            } else {
                $unitId = null;
            }

            $group = new PurchaseDemandGroup([
                'title' => (string) $data['title'],
                'notes' => $data['notes'] ?? null,
            ]);
            $group->forceFill([
                'group_number' => $this->numbers->next(),
                'status' => PurchaseDemandGroupStatus::Draft,
                'season_id' => $seasonId,
                'beneficiary_organization_unit_id' => $unitId,
                'created_by' => $actor->id,
            ]);
            $group->save();

            $this->security->record(AuthorizationSecurityEvent::DEMAND_GROUP_CREATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'purchase_demand_group',
                'entity_id' => $group->id,
                'group_number' => $group->group_number,
                'status' => $group->status->value,
            ], $request);

            return $group->fresh(['creator', 'season', 'beneficiaryUnit']) ?? $group;
        }, 5);
    }
}
