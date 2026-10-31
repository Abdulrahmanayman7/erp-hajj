<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\SuppliersAndProcurement\Enums\SupplierType;
use App\Modules\SuppliersAndProcurement\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_type' => SupplierType::Company,
            'display_name' => fake()->company(),
            'legal_name' => fake()->company(),
            'country_code' => 'SA',
            'legal_id_type' => null,
            'legal_id_value' => null,
            'notes' => null,
            'created_by' => User::factory(),
        ];
    }
}
