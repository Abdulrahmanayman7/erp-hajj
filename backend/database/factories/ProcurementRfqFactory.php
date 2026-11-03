<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\SuppliersAndProcurement\Enums\ProcurementRfqStatus;
use App\Modules\SuppliersAndProcurement\Models\ProcurementRfq;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProcurementRfq>
 */
class ProcurementRfqFactory extends Factory
{
    protected $model = ProcurementRfq::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rfq_number' => 'RFQ-'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'purchase_request_id' => PurchaseRequest::factory(),
            'season_id' => null,
            'status' => ProcurementRfqStatus::Draft,
            'title' => fake()->sentence(3),
            'notes' => null,
            'created_by' => User::factory(),
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => ProcurementRfqStatus::Published,
            'published_at' => now(),
        ]);
    }
}
