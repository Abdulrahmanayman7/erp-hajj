<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\SupplierEvaluationCriteriaPack;
use App\Modules\SuppliersAndProcurement\Models\SupplierEvaluationCriterion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class DeleteEvaluationCriterion
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(
        User $actor,
        SupplierEvaluationCriteriaPack $pack,
        SupplierEvaluationCriterion $criterion,
        Request $request,
    ): void {
        $tenant = $this->tenantContext->require();

        DB::transaction(function () use ($actor, $pack, $criterion, $request, $tenant): void {
            /** @var SupplierEvaluationCriteriaPack $locked */
            $locked = SupplierEvaluationCriteriaPack::query()->whereKey($pack->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isDraft()) {
                throw SupplierDomainException::criteriaPackNotDraft();
            }

            /** @var SupplierEvaluationCriterion $line */
            $line = SupplierEvaluationCriterion::query()->whereKey($criterion->id)->lockForUpdate()->firstOrFail();
            if ((int) $line->pack_id !== (int) $locked->id) {
                throw SupplierDomainException::criteriaPackInvalidStatus();
            }

            $lineId = $line->id;
            $line->delete();

            $locked->forceFill(['last_edited_by' => $actor->id])->save();
            $locked->recordEditor((int) $actor->id);

            $this->security->record(AuthorizationSecurityEvent::CRITERIA_PACK_UPDATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'supplier_evaluation_criterion',
                'entity_id' => $lineId,
                'criteria_pack_id' => $locked->id,
                'action' => 'deleted',
            ], $request);
        });
    }
}
