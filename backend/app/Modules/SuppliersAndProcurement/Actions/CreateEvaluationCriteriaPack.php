<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Enums\CriteriaPackStatus;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\SupplierCategory;
use App\Modules\SuppliersAndProcurement\Models\SupplierEvaluationCriteriaPack;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CreateEvaluationCriteriaPack
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, array $data, Request $request): SupplierEvaluationCriteriaPack
    {
        $tenant = $this->tenantContext->require();
        $categoryId = (int) $data['supplier_category_id'];

        return DB::transaction(function () use ($actor, $data, $request, $tenant, $categoryId): SupplierEvaluationCriteriaPack {
            $category = SupplierCategory::query()->whereKey($categoryId)->lockForUpdate()->first();
            if ($category === null) {
                throw SupplierDomainException::criteriaPackCategoryInvalid();
            }

            $family = SupplierEvaluationCriteriaPack::query()
                ->where('supplier_category_id', $categoryId)
                ->lockForUpdate()
                ->get();

            if ($family->contains(fn (SupplierEvaluationCriteriaPack $p): bool => $p->isDraft())) {
                throw SupplierDomainException::criteriaPackDraftExists();
            }

            $supersedesId = isset($data['supersedes_pack_id']) ? (int) $data['supersedes_pack_id'] : null;
            if ($supersedesId !== null) {
                $prior = $family->firstWhere('id', $supersedesId);
                if ($prior === null || (! $prior->isPublished() && $prior->status !== CriteriaPackStatus::Archived)) {
                    throw SupplierDomainException::criteriaPackCategoryInvalid();
                }
            }

            $pack = new SupplierEvaluationCriteriaPack([
                'notes' => $data['notes'] ?? null,
            ]);
            $pack->forceFill([
                'supplier_category_id' => $categoryId,
                'status' => CriteriaPackStatus::Draft,
                'version_no' => (int) $family->max('version_no') + 1,
                'prepared_by' => $actor->id,
                'last_edited_by' => $actor->id,
                'supersedes_pack_id' => $supersedesId,
            ]);

            try {
                $pack->save();
            } catch (UniqueConstraintViolationException) {
                throw SupplierDomainException::criteriaPackDraftExists();
            }

            $pack->recordEditor((int) $actor->id);

            $this->security->record(AuthorizationSecurityEvent::CRITERIA_PACK_CREATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'supplier_evaluation_criteria_pack',
                'entity_id' => $pack->id,
                'supplier_category_id' => $categoryId,
                'version_no' => $pack->version_no,
                'status' => $pack->status->value,
            ], $request);

            return $pack->load(['criteria', 'preparer', 'lastEditor', 'approver', 'category']);
        });
    }
}
