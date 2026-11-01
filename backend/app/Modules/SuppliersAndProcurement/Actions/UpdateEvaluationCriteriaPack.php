<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\SupplierEvaluationCriteriaPack;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class UpdateEvaluationCriteriaPack
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array{notes?: ?string}  $data
     */
    public function execute(
        User $actor,
        SupplierEvaluationCriteriaPack $pack,
        array $data,
        Request $request,
    ): SupplierEvaluationCriteriaPack {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $pack, $data, $request, $tenant): SupplierEvaluationCriteriaPack {
            /** @var SupplierEvaluationCriteriaPack $locked */
            $locked = SupplierEvaluationCriteriaPack::query()->whereKey($pack->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isDraft()) {
                throw SupplierDomainException::criteriaPackNotDraft();
            }

            if (array_key_exists('notes', $data)) {
                $locked->notes = $data['notes'];
            }
            $locked->last_edited_by = $actor->id;
            $locked->save();
            $locked->recordEditor((int) $actor->id);

            $this->security->record(AuthorizationSecurityEvent::CRITERIA_PACK_UPDATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'supplier_evaluation_criteria_pack',
                'entity_id' => $locked->id,
                'version_no' => $locked->version_no,
                'status' => $locked->status->value,
            ], $request);

            return $locked->fresh(['criteria', 'preparer', 'lastEditor', 'approver', 'category'])
                ?? $locked;
        });
    }
}
