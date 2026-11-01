<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Enums\CriteriaPackStatus;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\SupplierEvaluationCriteriaPack;
use App\Modules\SuppliersAndProcurement\Support\CriteriaWeightValidator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class PublishEvaluationCriteriaPack
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
        private readonly CriteriaWeightValidator $weights,
    ) {}

    public function execute(
        User $actor,
        SupplierEvaluationCriteriaPack $pack,
        Request $request,
    ): SupplierEvaluationCriteriaPack {
        $tenant = $this->tenantContext->require();

        try {
            return DB::transaction(function () use ($actor, $pack, $request, $tenant): SupplierEvaluationCriteriaPack {
                /** @var SupplierEvaluationCriteriaPack $locked */
                $locked = SupplierEvaluationCriteriaPack::query()->whereKey($pack->id)->lockForUpdate()->firstOrFail();

                if (! $locked->isDraft()) {
                    throw SupplierDomainException::criteriaPackNotDraft();
                }

                if ($locked->hasContributor((int) $actor->id)) {
                    throw SupplierDomainException::criteriaPackSodViolation();
                }

                $this->weights->assertPublishable($locked);

                $previous = SupplierEvaluationCriteriaPack::query()
                    ->where('supplier_category_id', $locked->supplier_category_id)
                    ->where('status', CriteriaPackStatus::Published)
                    ->whereKeyNot($locked->id)
                    ->lockForUpdate()
                    ->get();

                $now = now();

                foreach ($previous as $prior) {
                    $prior->forceFill([
                        'status' => CriteriaPackStatus::Archived,
                    ])->save();
                }

                $locked->forceFill([
                    'status' => CriteriaPackStatus::Published,
                    'approved_by' => $actor->id,
                    'approved_at' => $now,
                    'supersedes_pack_id' => $previous->first()?->id ?? $locked->supersedes_pack_id,
                ]);

                try {
                    $locked->save();
                } catch (UniqueConstraintViolationException) {
                    throw SupplierDomainException::criteriaPackInvalidStatus();
                }

                $this->security->record(AuthorizationSecurityEvent::CRITERIA_PACK_PUBLISHED, [
                    'tenant_id' => $tenant->id,
                    'actor_id' => $actor->id,
                    'entity_type' => 'supplier_evaluation_criteria_pack',
                    'entity_id' => $locked->id,
                    'supplier_category_id' => $locked->supplier_category_id,
                    'version_no' => $locked->version_no,
                    'status' => $locked->status->value,
                    'archived_prior_ids' => $previous->pluck('id')->all(),
                    'weight_sum' => $this->weights->sumActiveWeights($locked),
                ], $request);

                return $locked->fresh(['criteria', 'preparer', 'lastEditor', 'approver', 'category'])
                    ?? $locked;
            });
        } catch (SupplierDomainException $e) {
            if ($e->errorCode() === 'CRITERIA_PACK_SOD_VIOLATION') {
                $this->security->record(AuthorizationSecurityEvent::CRITERIA_PACK_SOD_VIOLATION, [
                    'tenant_id' => $tenant->id,
                    'actor_id' => $actor->id,
                    'entity_type' => 'supplier_evaluation_criteria_pack',
                    'entity_id' => $pack->id,
                    'attempted_action' => 'publish_criteria_pack',
                    'outcome' => 'denied',
                ], $request);
            }

            throw $e;
        }
    }
}
