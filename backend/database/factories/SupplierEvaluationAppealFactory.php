<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\SuppliersAndProcurement\Enums\EvaluationAppealStatus;
use App\Modules\SuppliersAndProcurement\Models\SupplierEvaluation;
use App\Modules\SuppliersAndProcurement\Models\SupplierEvaluationAppeal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierEvaluationAppeal>
 */
class SupplierEvaluationAppealFactory extends Factory
{
    protected $model = SupplierEvaluationAppeal::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'evaluation_id' => SupplierEvaluation::factory(),
            'reason' => 'سبب اعتراض كافٍ للاختبار الآلي',
            'status' => EvaluationAppealStatus::Open,
            'open_slot' => 0,
            'filed_by' => User::factory(),
            'filed_at' => now(),
            'reviewed_by' => null,
            'reviewed_at' => null,
            'review_outcome_note' => null,
            'resulting_evaluation_id' => null,
        ];
    }
}
