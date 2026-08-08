<?php

namespace Database\Factories;

use App\Modules\Authorization\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    protected $model = Role::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $code = 'role_'.Str::lower(Str::random(8));

        return [
            'name' => fake()->unique()->words(2, true),
            'code' => $code,
            'description' => fake()->optional()->sentence(),
            'is_system' => false,
            'is_active' => true,
            'created_by' => null,
        ];
    }

    public function system(string $code = 'custom_system'): static
    {
        return $this->state(fn (): array => [
            'code' => $code,
            'is_system' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }
}
