<?php

namespace Database\Factories;

use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SeasonalTemplates\Enums\TemplateStatus;
use App\Modules\SeasonalTemplates\Models\SeasonTemplate;
use App\Modules\SeasonalTemplates\Support\SafeCopyAllowlist;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeasonTemplate>
 */
class SeasonTemplateFactory extends Factory
{
    protected $model = SeasonTemplate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'قالب '.fake()->unique()->numberBetween(1, 99999),
            'description' => null,
            'status' => TemplateStatus::Draft,
            'matrix_version' => SafeCopyAllowlist::MATRIX_VERSION,
            'created_by' => function (): int {
                $attrs = [];
                $tenant = app(TenantContext::class)->get();
                if ($tenant !== null) {
                    $attrs['tenant_id'] = $tenant->id;
                }

                return User::factory()->create($attrs)->id;
            },
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => TemplateStatus::Approved,
            'approved_at' => now(),
        ]);
    }
}
