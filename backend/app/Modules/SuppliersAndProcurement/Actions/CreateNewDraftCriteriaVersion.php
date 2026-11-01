<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Models\User;
use App\Modules\SuppliersAndProcurement\Enums\CriteriaPackStatus;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\SupplierEvaluationCriteriaPack;
use App\Modules\SuppliersAndProcurement\Models\SupplierEvaluationCriterion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Spawns a new draft version from a published (or archived) pack, copying active criteria lines.
 */
final class CreateNewDraftCriteriaVersion
{
    public function __construct(
        private readonly CreateEvaluationCriteriaPack $createPack,
    ) {}

    public function execute(
        User $actor,
        SupplierEvaluationCriteriaPack $source,
        Request $request,
    ): SupplierEvaluationCriteriaPack {
        return DB::transaction(function () use ($actor, $source, $request): SupplierEvaluationCriteriaPack {
            /** @var SupplierEvaluationCriteriaPack $locked */
            $locked = SupplierEvaluationCriteriaPack::query()->whereKey($source->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [CriteriaPackStatus::Published, CriteriaPackStatus::Archived], true)) {
                throw SupplierDomainException::criteriaPackInvalidStatus();
            }

            $draft = $this->createPack->execute($actor, [
                'supplier_category_id' => $locked->supplier_category_id,
                'notes' => $locked->notes,
                'supersedes_pack_id' => $locked->id,
            ], $request);

            $sort = 1;
            foreach ($locked->activeCriteria()->orderBy('sort_order')->orderBy('id')->get() as $line) {
                /** @var SupplierEvaluationCriterion $line */
                $copy = new SupplierEvaluationCriterion([
                    'name' => $line->name,
                    'weight' => $line->weight,
                    'sort_order' => $sort++,
                    'is_active' => true,
                ]);
                $copy->forceFill([
                    'pack_id' => $draft->id,
                ]);
                $copy->save();
            }

            return $draft->fresh(['criteria', 'preparer', 'lastEditor', 'approver', 'category'])
                ?? $draft;
        });
    }
}
