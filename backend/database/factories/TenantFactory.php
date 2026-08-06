<?php

namespace Database\Factories;

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_code' => 'tenant-'.fake()->unique()->numberBetween(1000, 999999),
            'name' => 'منشأة '.fake()->unique()->numberBetween(1000, 999999),
            'status' => TenantStatus::Active,
            'locale' => 'ar',
            'timezone' => 'Asia/Riyadh',
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (): array => ['status' => TenantStatus::Pending]);
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => [
            'status' => TenantStatus::Suspended,
        ])->afterCreating(function (Tenant $tenant): void {
            $tenant->forceFill(['suspended_at' => now()])->saveQuietly();
        });
    }

    public function archived(): static
    {
        return $this->state(fn (): array => [
            'status' => TenantStatus::Archived,
        ])->afterCreating(function (Tenant $tenant): void {
            $tenant->forceFill(['archived_at' => now()])->saveQuietly();
        });
    }
}
