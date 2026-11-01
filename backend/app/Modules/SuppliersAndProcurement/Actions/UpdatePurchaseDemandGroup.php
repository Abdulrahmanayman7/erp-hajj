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
use App\Modules\SuppliersAndProcurement\Support\PurchaseDemandGroupEngagementGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class UpdatePurchaseDemandGroup
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
        private readonly PurchaseDemandGroupEngagementGuard $engagement,
    ) {}

    /**
     * @param  array{title?: string, notes?: ?string, season_id?: ?int, beneficiary_organization_unit_id?: ?int}  $data
     */
    public function execute(
        User $actor,
        PurchaseDemandGroup $group,
        array $data,
        Request $request,
    ): PurchaseDemandGroup {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $group, $data, $request, $tenant): PurchaseDemandGroup {
            $locked = $this->engagement->lockGroup((int) $group->id);

            if ($locked->status === PurchaseDemandGroupStatus::Closed) {
                throw SupplierDomainException::dgNotEditable();
            }

            if (! $locked->status->allowsHeaderEdit()) {
                throw SupplierDomainException::dgNotEditable();
            }

            $updates = [];
            if (array_key_exists('title', $data)) {
                $updates['title'] = (string) $data['title'];
            }
            if (array_key_exists('notes', $data)) {
                $updates['notes'] = $data['notes'];
            }

            $wantsScopeChange = array_key_exists('season_id', $data)
                || array_key_exists('beneficiary_organization_unit_id', $data);

            if ($wantsScopeChange) {
                if (! $locked->status->allowsScopeEdit()) {
                    throw SupplierDomainException::dgNotEditable();
                }

                if (array_key_exists('season_id', $data)) {
                    $seasonId = $data['season_id'] !== null ? (int) $data['season_id'] : null;
                    if ($seasonId !== null && $seasonId > 0) {
                        if (Season::query()->whereKey($seasonId)->doesntExist()) {
                            throw SupplierDomainException::prSeasonInvalid();
                        }
                        $updates['season_id'] = $seasonId;
                    } else {
                        $updates['season_id'] = null;
                    }
                }

                if (array_key_exists('beneficiary_organization_unit_id', $data)) {
                    $unitId = $data['beneficiary_organization_unit_id'] !== null
                        ? (int) $data['beneficiary_organization_unit_id']
                        : null;
                    if ($unitId !== null && $unitId > 0) {
                        if (OrganizationUnit::query()->whereKey($unitId)->doesntExist()) {
                            throw SupplierDomainException::prOrgUnitNotFound();
                        }
                        $updates['beneficiary_organization_unit_id'] = $unitId;
                    } else {
                        $updates['beneficiary_organization_unit_id'] = null;
                    }
                }
            }

            if ($updates !== []) {
                $locked->fill(array_intersect_key($updates, array_flip(['title', 'notes'])));
                $scope = array_intersect_key($updates, array_flip(['season_id', 'beneficiary_organization_unit_id']));
                if ($scope !== []) {
                    $locked->forceFill($scope);
                }
                $locked->save();
            }

            $this->security->record(AuthorizationSecurityEvent::DEMAND_GROUP_UPDATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'purchase_demand_group',
                'entity_id' => $locked->id,
                'group_number' => $locked->group_number,
                'status' => $locked->status->value,
            ], $request);

            return $locked->fresh(['creator', 'season', 'beneficiaryUnit', 'activeMemberships']) ?? $locked;
        });
    }
}
