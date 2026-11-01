<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\SupplierEligibilityRequirementSet;
use App\Modules\SuppliersAndProcurement\Support\EligibilityRequirementDefWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class UpdateEligibilityRequirementSetDraft
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly EligibilityRequirementDefWriter $defs,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, SupplierEligibilityRequirementSet $set, array $data, Request $request): SupplierEligibilityRequirementSet
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $set, $data, $request, $tenant): SupplierEligibilityRequirementSet {
            $locked = SupplierEligibilityRequirementSet::query()->whereKey($set->id)->lockForUpdate()->firstOrFail();

            // C1: approved versions are immutable; changes need a new draft.
            if (! $locked->isDraft()) {
                throw SupplierDomainException::eligibilityConfigImmutable();
            }

            $changed = [];
            if (array_key_exists('name', $data)) {
                $locked->name = $data['name'];
                $changed[] = 'name';
            }

            // Last editor is tracked for SoD (C3): the editor can no longer approve this draft.
            $locked->forceFill(['last_edited_by' => $actor->id])->save();
            // Every editor is remembered (M5): an intermediate editor can never approve either.
            $locked->recordEditor((int) $actor->id);

            if (array_key_exists('requirement_defs', $data)) {
                $this->defs->replace($locked, $data['requirement_defs']);
                $changed[] = 'requirement_defs';
            }

            $this->security->record(AuthorizationSecurityEvent::SUPPLIER_ELIGIBILITY_CONFIG_UPDATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'supplier_eligibility_requirement_set',
                'entity_id' => $locked->id,
                'config_key' => $locked->config_key,
                'version_no' => $locked->version_no,
                'changed_fields' => $changed,
            ], $request);

            return $locked->load(['requirementDefs', 'author', 'lastEditor', 'approver']);
        });
    }
}
