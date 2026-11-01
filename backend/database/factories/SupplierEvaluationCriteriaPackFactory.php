<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\SuppliersAndProcurement\Enums\CriteriaPackStatus;
use App\Modules\SuppliersAndProcurement\Models\SupplierCategory;
use App\Modules\SuppliersAndProcurement\Models\SupplierEvaluationCriteriaPack;
use App\Modules\SuppliersAndProcurement\Models\SupplierEvaluationCriterion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierEvaluationCriteriaPack>
 */
class SupplierEvaluationCriteriaPackFactory extends Factory
{
    protected $model = SupplierEvaluationCriteriaPack::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_category_id' => SupplierCategory::factory(),
            'status' => CriteriaPackStatus::Draft,
            'version_no' => 1,
            'prepared_by' => User::factory(),
            'last_edited_by' => null,
            'approved_by' => null,
            'approved_at' => null,
            'supersedes_pack_id' => null,
            'notes' => null,
        ];
    }

    /**
     * @param  list<array{name: string, weight: float|string, sort_order?: int}>  $lines
     */
    public static function withLines(SupplierEvaluationCriteriaPack $pack, array $lines): SupplierEvaluationCriteriaPack
    {
        foreach ($lines as $i => $line) {
            $criterion = new SupplierEvaluationCriterion([
                'name' => $line['name'],
                'weight' => $line['weight'],
                'sort_order' => $line['sort_order'] ?? ($i + 1),
                'is_active' => $line['is_active'] ?? true,
            ]);
            $criterion->forceFill(['pack_id' => $pack->id]);
            $criterion->save();
        }

        return $pack->fresh(['criteria']) ?? $pack;
    }
}
