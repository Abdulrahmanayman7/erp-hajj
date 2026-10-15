<?php

namespace Database\Factories;

use App\Modules\SeasonPilgrims\Enums\SeasonStatus;
use App\Modules\SeasonPilgrims\Models\Season;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Season>
 */
class SeasonFactory extends Factory
{
    protected $model = Season::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = fake()->unique()->numberBetween(1446, 1599);

        return [
            'code' => 'H'.$year,
            'name' => 'موسم حج '.$year,
            'hajj_year' => $year,
            'operational_starts_on' => '2027-05-01',
            'operational_ends_on' => '2027-06-30',
            'status' => SeasonStatus::Draft,
            'notes' => null,
            'created_by' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => [
            'status' => SeasonStatus::Active,
            'first_activated_at' => now(),
            'activated_at' => now(),
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (): array => [
            'status' => SeasonStatus::Closed,
            'first_activated_at' => now()->subMonth(),
            'activated_at' => now()->subMonth(),
            'closed_at' => now(),
        ]);
    }
}
