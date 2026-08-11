<?php

namespace Database\Factories;

use App\Modules\Authorization\Models\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Permission>
 */
class PermissionFactory extends Factory
{
    protected $model = Permission::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $module = fake()->unique()->slug(1);

        return [
            'name' => $module.'.'.fake()->unique()->slug(1),
            'display_name' => fake()->words(2, true),
            'module' => $module,
            'description' => fake()->optional()->sentence(),
        ];
    }
}
