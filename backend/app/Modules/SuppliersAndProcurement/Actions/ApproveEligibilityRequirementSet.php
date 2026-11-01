<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Enums\EligibilityConfigStatus;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\SupplierEligibilityRequirementSet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ApproveEligibilityRequirementSet
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, SupplierEligibilityRequirementSet $set, ?string $comment, Request $request): SupplierEligibilityRequirementSet
    {
        $tenant = $this->tenantContext->require();

        try {
            return DB::transaction(function () use ($actor, $set, $comment, $request, $tenant): SupplierEligibilityRequirementSet {
                $locked = SupplierEligibilityRequirementSet::query()->whereKey($set->id)->lockForUpdate()->firstOrFail();

                if (! $locked->isDraft()) {
                    throw SupplierDomainException::eligibilityConfigNotDraft();
                }

                // C3: the author, the last editor and every intermediate editor can never approve.
                if ($locked->hasContributor((int) $actor->id)) {
                    throw SupplierDomainException::sodViolation();
                }

                if (! $locked->requirementDefs()->exists()) {
                    throw SupplierDomainException::eligibilityDefsEmpty();
                }

                // M1=A (Owner): an approvable pack needs at least one mandatory requirement.
                if (! $locked->requirementDefs()->where('is_mandatory', true)->exists()) {
                    throw SupplierDomainException::eligibilityDefsNoMandatory();
                }

                $locked->forceFill([
                    'status' => EligibilityConfigStatus::Approved,
                    'approved_by' => $actor->id,
                    'approved_at' => now(),
                ])->save();

                $this->security->record(AuthorizationSecurityEvent::SUPPLIER_ELIGIBILITY_CONFIG_APPROVED, [
                    'tenant_id' => $tenant->id,
                    'actor_id' => $actor->id,
                    'entity_type' => 'supplier_eligibility_requirement_set',
                    'entity_id' => $locked->id,
                    'config_key' => $locked->config_key,
                    'version_no' => $locked->version_no,
                    'comment' => $comment,
                ], $request);

                return $locked->load(['requirementDefs', 'author', 'lastEditor', 'approver']);
            });
        } catch (SupplierDomainException $e) {
            if ($e->errorCode() === 'SUPPLIER_SOD_VIOLATION') {
                $this->security->record(AuthorizationSecurityEvent::SUPPLIER_SOD_VIOLATION, [
                    'tenant_id' => $tenant->id,
                    'actor_id' => $actor->id,
                    'entity_type' => 'supplier_eligibility_requirement_set',
                    'entity_id' => $set->id,
                    'attempted_action' => 'approve_eligibility_config',
                ], $request);
            }

            throw $e;
        }
    }
}
