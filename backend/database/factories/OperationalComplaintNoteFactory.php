<?php

namespace Database\Factories;

use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\RisksAndComplaints\Models\OperationalComplaint;
use App\Modules\RisksAndComplaints\Models\OperationalComplaintNote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OperationalComplaintNote>
 */
class OperationalComplaintNoteFactory extends Factory
{
    protected $model = OperationalComplaintNote::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'complaint_id' => function (): int {
                return OperationalComplaint::factory()->create()->id;
            },
            'body' => fake()->sentence(),
            'created_by' => function (): int {
                $attrs = [];
                $tenant = app(TenantContext::class)->get();
                if ($tenant !== null) {
                    $attrs['tenant_id'] = $tenant->id;
                }

                return User::factory()->create($attrs)->id;
            },
            'created_at' => now(),
        ];
    }
}
