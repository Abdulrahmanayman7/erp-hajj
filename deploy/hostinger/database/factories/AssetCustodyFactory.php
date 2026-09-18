<?php

namespace Database\Factories;

use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Assets\Enums\CustodyStatus;
use App\Modules\Assets\Models\Asset;
use App\Modules\Assets\Models\AssetCustody;
use App\Modules\Employees\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssetCustody>
 */
class AssetCustodyFactory extends Factory
{
    protected $model = AssetCustody::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'asset_id' => Asset::factory(),
            'employee_id' => Employee::factory(),
            'status' => CustodyStatus::Active,
            'assigned_at' => now(),
            'expected_return_at' => null,
            'returned_at' => null,
            'assigned_by' => function (): int {
                $attrs = [];
                $tenant = app(TenantContext::class)->get();
                if ($tenant !== null) {
                    $attrs['tenant_id'] = $tenant->id;
                }

                return User::factory()->create($attrs)->id;
            },
            'returned_by' => null,
            'condition_at_assignment' => null,
            'condition_at_return' => null,
            'assignment_notes' => null,
            'return_notes' => null,
            'correlation_id' => null,
        ];
    }
}
