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

final class CreateEligibilityRequirementSetDraft
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly EligibilityRequirementDefWriter $defs,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, array $data, Request $request): SupplierEligibilityRequirementSet
    {
        $tenant = $this->tenantContext->require();
        $configKey = (string) $data['config_key'];

        return DB::transaction(function () use ($actor, $data, $request, $tenant, $configKey): SupplierEligibilityRequirementSet {
            $family = SupplierEligibilityRequirementSet::query()
                ->where('config_key', $configKey)
                ->lockForUpdate()
                ->get();

            if ($family->contains(fn (SupplierEligibilityRequirementSet $s): bool => $s->isDraft())) {
                throw SupplierDomainException::eligibilityConfigDraftExists();
            }

            $set = new SupplierEligibilityRequirementSet([
                'config_key' => $configKey,
                'name' => $data['name'],
            ]);
            $set->forceFill([
                'status' => EligibilityConfigStatus::Draft,
                'version_no' => (int) $family->max('version_no') + 1,
                'authored_by' => $actor->id,
                'last_edited_by' => $actor->id,
            ]);

            try {
                $set->save();
            } catch (UniqueConstraintViolationException) {
                throw SupplierDomainException::eligibilityConfigDraftExists();
            }

            $set->recordEditor((int) $actor->id);
            $this->defs->replace($set, $data['requirement_defs']);

            $this->security->record(AuthorizationSecurityEvent::SUPPLIER_ELIGIBILITY_CONFIG_CREATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'supplier_eligibility_requirement_set',
                'entity_id' => $set->id,
                'config_key' => $set->config_key,
                'version_no' => $set->version_no,
                'status' => $set->status->value,
                'definition_count' => count($data['requirement_defs']),
            ], $request);

            return $set->load(['requirementDefs', 'author', 'lastEditor', 'approver']);
        });
    }
}
