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

final class UpsertEvaluationCriterion
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(
        User $actor,
        SupplierEvaluationCriteriaPack $pack,
        array $data,
        Request $request,
        ?SupplierEvaluationCriterion $existing = null,
    ): SupplierEvaluationCriterion {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $pack, $data, $request, $existing, $tenant): SupplierEvaluationCriterion {
            /** @var SupplierEvaluationCriteriaPack $locked */
            $locked = SupplierEvaluationCriteriaPack::query()->whereKey($pack->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isDraft()) {
                throw SupplierDomainException::criteriaPackNotDraft();
            }

            if ($existing !== null) {
                /** @var SupplierEvaluationCriterion $line */
                $line = SupplierEvaluationCriterion::query()->whereKey($existing->id)->lockForUpdate()->firstOrFail();
                if ((int) $line->pack_id !== (int) $locked->id) {
                    throw SupplierDomainException::criteriaPackInvalidStatus();
                }
            } else {
                $line = new SupplierEvaluationCriterion;
                $line->forceFill(['pack_id' => $locked->id]);
            }

            $payload = [];
            if (array_key_exists('name', $data) || $existing === null) {
                $payload['name'] = $data['name'];
            }
            if (array_key_exists('weight', $data) || $existing === null) {
                $payload['weight'] = $data['weight'];
            }
            if (array_key_exists('sort_order', $data)) {
                $payload['sort_order'] = (int) $data['sort_order'];
            } elseif ($existing === null) {
                $payload['sort_order'] = (int) ($locked->criteria()->max('sort_order') ?? 0) + 1;
            }
            if (array_key_exists('is_active', $data)) {
                $payload['is_active'] = (bool) $data['is_active'];
            } elseif ($existing === null) {
                $payload['is_active'] = true;
            }

            $line->fill($payload);
            $line->save();

            $locked->forceFill(['last_edited_by' => $actor->id])->save();
            $locked->recordEditor((int) $actor->id);

            $this->security->record(AuthorizationSecurityEvent::CRITERIA_PACK_UPDATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'supplier_evaluation_criterion',
                'entity_id' => $line->id,
                'criteria_pack_id' => $locked->id,
                'action' => $existing === null ? 'created' : 'updated',
            ], $request);

            return $line->fresh() ?? $line;
        });
    }
}
