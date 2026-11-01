<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\SuppliersAndProcurement\Enums\EvaluationKind;
use App\Modules\SuppliersAndProcurement\Enums\EvaluationStatus;
use App\Modules\SuppliersAndProcurement\Models\Supplier;
use App\Modules\SuppliersAndProcurement\Models\SupplierCategory;
use App\Modules\SuppliersAndProcurement\Models\SupplierEvaluation;
use App\Modules\SuppliersAndProcurement\Models\SupplierEvaluationCriteriaPack;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SupplierEvaluation>
 */
class SupplierEvaluationFactory extends Factory
{
    protected $model = SupplierEvaluation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'evaluation_number' => 'SEV-'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'supplier_id' => Supplier::factory(),
            'supplier_category_id' => SupplierCategory::factory(),
            'evaluation_kind' => EvaluationKind::Qualification,
            'season_id' => null,
            'status' => EvaluationStatus::Draft,
            'criteria_pack_id' => SupplierEvaluationCriteriaPack::factory(),
            'criteria_pack_version_no' => 1,
            'total_score' => null,
            'monitoring_recommended' => false,
            'prepared_by' => User::factory(),
            'version_no' => 1,
            'evaluation_family_key' => (string) Str::uuid(),
            'supersedes_evaluation_id' => null,
            'origin_appeal_id' => null,
            'draft_scores' => null,
            'notes' => null,
        ];
    }
}
