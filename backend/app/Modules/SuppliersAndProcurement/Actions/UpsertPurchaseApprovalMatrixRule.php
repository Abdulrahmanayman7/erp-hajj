<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestKind;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\PurchaseApprovalMatrix;
use App\Modules\SuppliersAndProcurement\Models\PurchaseApprovalMatrixRule;
use App\Modules\SuppliersAndProcurement\Support\PurchaseApprovalMatrixPublishValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class UpsertPurchaseApprovalMatrixRule
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
        private readonly PurchaseApprovalMatrixPublishValidator $validator,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(
        User $actor,
        PurchaseApprovalMatrix $matrix,
        array $data,
        Request $request,
        ?PurchaseApprovalMatrixRule $existing = null,
    ): PurchaseApprovalMatrixRule {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $matrix, $data, $request, $existing, $tenant): PurchaseApprovalMatrixRule {
            /** @var PurchaseApprovalMatrix $locked */
            $locked = PurchaseApprovalMatrix::query()->whereKey($matrix->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isDraft()) {
                throw SupplierDomainException::matrixNotDraft();
            }

            if ($existing !== null) {
                /** @var PurchaseApprovalMatrixRule $rule */
                $rule = PurchaseApprovalMatrixRule::query()->whereKey($existing->id)->lockForUpdate()->firstOrFail();
                if ((int) $rule->matrix_id !== (int) $locked->id) {
                    throw SupplierDomainException::matrixNotDraft();
                }
            } else {
                $rule = new PurchaseApprovalMatrixRule;
                $rule->forceFill(['matrix_id' => $locked->id]);
            }

            $minAmount = array_key_exists('min_amount', $data) ? $data['min_amount'] : ($existing?->min_amount);
            $maxAmount = array_key_exists('max_amount', $data) ? $data['max_amount'] : ($existing?->max_amount);
            $this->validator->assertAmountRange($minAmount, $maxAmount);

            $orgUnitId = array_key_exists('organization_unit_id', $data)
                ? $data['organization_unit_id']
                : ($existing?->organization_unit_id);
            $seasonId = array_key_exists('season_id', $data)
                ? $data['season_id']
                : ($existing?->season_id);

            $resolvedOuId = $this->resolveOptionalOrgUnit(
                $orgUnitId !== null && $orgUnitId !== '' ? (int) $orgUnitId : null,
            );
            $resolvedSeasonId = $this->resolveOptionalSeason(
                $seasonId !== null && $seasonId !== '' ? (int) $seasonId : null,
            );

            $requestKind = array_key_exists('request_kind', $data)
                ? $data['request_kind']
                : ($existing?->request_kind?->value);
            $appliesWhenUnpriced = array_key_exists('applies_when_unpriced', $data)
                ? (bool) $data['applies_when_unpriced']
                : (bool) ($existing?->applies_when_unpriced ?? false);

            $rule->fill([
                'min_amount' => $minAmount,
                'max_amount' => $maxAmount,
                'request_kind' => $requestKind !== null && $requestKind !== ''
                    ? PurchaseRequestKind::from((string) $requestKind)
                    : null,
                'applies_when_unpriced' => $appliesWhenUnpriced,
            ]);
            $rule->forceFill([
                'organization_unit_id' => $resolvedOuId,
                'season_id' => $resolvedSeasonId,
            ]);

            $rule->specificity_hint = $this->validator->specificityScore($rule);
            $rule->save();

            $event = $existing === null
                ? AuthorizationSecurityEvent::MATRIX_RULE_CREATED
                : AuthorizationSecurityEvent::MATRIX_RULE_UPDATED;

            $this->security->record($event, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'purchase_approval_matrix_rule',
                'entity_id' => $rule->id,
                'matrix_id' => $locked->id,
                'applies_when_unpriced' => (bool) $rule->applies_when_unpriced,
                'specificity_hint' => $rule->specificity_hint,
            ], $request);

            return $rule->fresh(['matrix', 'organizationUnit', 'season']) ?? $rule;
        });
    }

    private function resolveOptionalOrgUnit(?int $unitId): ?int
    {
        if ($unitId === null || $unitId <= 0) {
            return null;
        }

        $unit = OrganizationUnit::query()->whereKey($unitId)->first();
        if ($unit === null) {
            throw SupplierDomainException::prOrgUnitInvalid();
        }

        return (int) $unit->id;
    }

    private function resolveOptionalSeason(?int $seasonId): ?int
    {
        if ($seasonId === null || $seasonId <= 0) {
            return null;
        }

        $season = Season::query()->whereKey($seasonId)->first();
        if ($season === null) {
            throw SupplierDomainException::prSeasonInvalid();
        }

        return (int) $season->id;
    }
}
