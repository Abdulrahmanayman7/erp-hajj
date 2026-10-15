<?php

namespace Database\Factories;

use App\Modules\SeasonPilgrims\Models\Pilgrim;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pilgrim>
 */
class PilgrimFactory extends Factory
{
    protected $model = Pilgrim::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'full_name' => fake()->name(),
            'gender' => null,
            'nationality' => null,
            'phone' => null,
            'notes' => null,
            'created_by' => null,
        ];
    }
}
