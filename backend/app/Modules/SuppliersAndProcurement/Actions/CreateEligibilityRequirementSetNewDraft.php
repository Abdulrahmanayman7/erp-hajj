<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Enums\EligibilityConfigStatus;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\SupplierEligibilityRequirementSet;
use App\Modules\SuppliersAndProcurement\Support\EligibilityRequirementDefWriter;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Creates the next draft version from an approved version (copies definitions);
 * the approved source is never mutated (C1).
 */
final class CreateEligibilityRequirementSetNewDraft
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly EligibilityRequirementDefWriter $defs,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, SupplierEligibilityRequirementSet $source, Request $request): SupplierEligibilityRequirementSet
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $source, $request, $tenant): SupplierEligibilityRequirementSet {
            $family = SupplierEligibilityRequirementSet::query()
                ->where('config_key', $source->config_key)
                ->lockForUpdate()
                ->get();

            $lockedSource = $family->firstWhere('id', $source->id);
            if ($lockedSource === null || ! $lockedSource->isApproved()) {
                throw SupplierDomainException::eligibilityConfigNotApproved();
            }

            if ($family->contains(fn (SupplierEligibilityRequirementSet $s): bool => $s->isDraft())) {
                throw SupplierDomainException::eligibilityConfigDraftExists();
            }

            $draft = new SupplierEligibilityRequirementSet([
                'config_key' => $lockedSource->config_key,
                'name' => $lockedSource->name,
            ]);
            $draft->forceFill([
                'status' => EligibilityConfigStatus::Draft,
                'version_no' => (int) $family->max('version_no') + 1,
                'authored_by' => $actor->id,
                'last_edited_by' => $actor->id,
            ]);

            try {
                $draft->save();
            } catch (UniqueConstraintViolationException) {
                throw SupplierDomainException::eligibilityConfigDraftExists();
            }

            $draft->recordEditor((int) $actor->id);
            $this->defs->copy($lockedSource, $draft);

            $this->security->record(AuthorizationSecurityEvent::SUPPLIER_ELIGIBILITY_CONFIG_CREATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'supplier_eligibility_requirement_set',
                'entity_id' => $draft->id,
                'config_key' => $draft->config_key,
                'version_no' => $draft->version_no,
                'status' => $draft->status->value,
                'copied_from_set_id' => $lockedSource->id,
            ], $request);

            return $draft->load(['requirementDefs', 'author', 'lastEditor', 'approver']);
        });
    }
}
