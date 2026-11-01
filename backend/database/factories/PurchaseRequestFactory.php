<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestKind;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestPriority;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestStatus;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseRequest>
 */
class PurchaseRequestFactory extends Factory
{
    protected $model = PurchaseRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pr_number' => 'PR-'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'version_no' => 1,
            'status' => PurchaseRequestStatus::Draft,
            'request_kind' => PurchaseRequestKind::Administrative,
            'season_id' => null,
            'title' => fake()->sentence(3),
            'purpose' => fake()->sentence(8),
            'priority' => PurchaseRequestPriority::Normal,
            'urgency_justification' => null,
            'requested_by' => User::factory(),
            'beneficiary_organization_unit_id' => OrganizationUnit::factory(),
            'optional_supplier_id' => null,
            'currency' => 'SAR',
            'estimated_total_priced' => 0,
            'has_unpriced_lines' => false,
            'matrix_id' => null,
            'matrix_version_no' => null,
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn (): array => [
            'status' => PurchaseRequestStatus::Submitted,
            'submitted_at' => now(),
        ]);
    }
}
