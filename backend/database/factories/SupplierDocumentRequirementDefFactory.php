<?php

namespace Database\Factories;

use App\Modules\SuppliersAndProcurement\Enums\SupplierType;
use App\Modules\SuppliersAndProcurement\Models\SupplierDocumentRequirementDef;
use App\Modules\SuppliersAndProcurement\Models\SupplierEligibilityRequirementSet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierDocumentRequirementDef>
 */
class SupplierDocumentRequirementDefFactory extends Factory
{
    protected $model = SupplierDocumentRequirementDef::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'requirement_set_id' => SupplierEligibilityRequirementSet::factory(),
            'requirement_key' => 'commercial_registration',
            'label_ar' => 'السجل التجاري',
            'description' => null,
            'supplier_type' => SupplierType::Company,
            'country_code' => null,
            'supplier_category_id' => null,
            'is_mandatory' => true,
            'default_validity_days' => null,
        ];
    }
}
