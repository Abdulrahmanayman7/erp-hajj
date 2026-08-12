<?php

namespace Database\Factories;

use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Notifications\Enums\NotificationSeverity;
use App\Modules\Notifications\Enums\NotificationType;
use App\Modules\Notifications\Models\Notification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'recipient_user_id' => function (): int {
                $tenant = app(TenantContext::class)->get();
                $attrs = [];
                if ($tenant !== null) {
                    $attrs['tenant_id'] = $tenant->id;
                }

                return User::factory()->create($attrs)->id;
            },
            'type' => NotificationType::TaskAssigned,
            'title' => 'إشعار اختبار',
            'body' => 'نص إشعار للاختبار',
            'severity' => NotificationSeverity::Info,
            'entity_type' => 'task',
            'entity_id' => 1,
            'dedupe_key' => null,
            'read_at' => null,
            'correlation_id' => null,
        ];
    }

    public function unread(): static
    {
        return $this->state(fn (): array => ['read_at' => null]);
    }

    public function read(): static
    {
        return $this->state(fn (): array => ['read_at' => now()]);
    }

    public function forRecipient(User $user): static
    {
        return $this->state(fn (): array => [
            'recipient_user_id' => $user->id,
        ]);
    }
}
