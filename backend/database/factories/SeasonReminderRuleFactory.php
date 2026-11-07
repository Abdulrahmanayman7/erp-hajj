<?php

namespace Database\Factories;

use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Notifications\Enums\NotificationSeverity;
use App\Modules\SeasonalTemplates\Enums\SeasonReminderRuleStatus;
use App\Modules\SeasonalTemplates\Enums\SeasonReminderTriggerMode;
use App\Modules\SeasonalTemplates\Models\SeasonReminderRule;
use App\Modules\SeasonPilgrims\Models\Season;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeasonReminderRule>
 */
class SeasonReminderRuleFactory extends Factory
{
    protected $model = SeasonReminderRule::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'season_id' => fn (): int => Season::factory()->create()->id,
            'code' => 'REM-'.fake()->unique()->numberBetween(1, 999999),
            'title' => 'تذكير '.fake()->unique()->numberBetween(1, 99999),
            'body' => null,
            'severity' => NotificationSeverity::Info,
            'trigger_mode' => SeasonReminderTriggerMode::AbsoluteDate,
            'trigger_on' => now()->addDays(10)->toDateString(),
            'offset_days' => null,
            'recipient_mode' => SeasonReminderRule::RECIPIENT_MODE_PERMISSION_HOLDERS,
            'recipient_permission' => 'seasons.view',
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

    public function configure(): static
    {
        return $this->afterMaking(function (SeasonReminderRule $rule): void {
            if ($rule->getAttribute('status') === null) {
                $rule->forceFill(['status' => SeasonReminderRuleStatus::Draft]);
            }
        });
    }

    public function status(SeasonReminderRuleStatus $status): static
    {
        return $this->afterMaking(fn (SeasonReminderRule $rule) => $rule->forceFill(['status' => $status]));
    }

    public function active(): static
    {
        return $this->status(SeasonReminderRuleStatus::Active);
    }
}
