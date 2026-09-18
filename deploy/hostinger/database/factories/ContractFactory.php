<?php

namespace Database\Factories;

use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Contracts\Enums\ContractStatus;
use App\Modules\Contracts\Enums\CounterpartyKind;
use App\Modules\Contracts\Models\Contract;
use App\Modules\Contracts\Models\ContractCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contract>
 */
class ContractFactory extends Factory
{
    protected $model = Contract::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'contract_category_id' => ContractCategory::factory(),
            'status' => ContractStatus::Draft,
            'counterparty_name' => fake()->company(),
            'counterparty_kind' => CounterpartyKind::Organization,
            'employee_id' => null,
            'organization_unit_id' => null,
            'start_date' => now()->toDateString(),
            'end_date' => null,
            'value' => null,
            'currency' => 'SAR',
            'notes' => null,
            'created_by' => function (): int {
                $attrs = [];
                $tenant = app(TenantContext::class)->get();
                if ($tenant !== null) {
                    $attrs['tenant_id'] = $tenant->id;
                }

                return User::factory()->create($attrs)->id;
            },
            'renewed_from_contract_id' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => [
            'status' => ContractStatus::Draft,
        ]);
    }

    public function executing(): static
    {
        return $this->state(fn (): array => [
            'status' => ContractStatus::Executing,
            'end_date' => now()->addDays(30)->toDateString(),
        ]);
    }

    public function expiredEndDate(): static
    {
        return $this->state(fn (): array => [
            'status' => ContractStatus::Executing,
            'end_date' => now()->subDay()->toDateString(),
        ]);
    }
}
