<?php

namespace App\Modules\Notifications\Actions;

use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Notifications\Enums\NotificationSeverity;
use App\Modules\Notifications\Enums\NotificationType;
use App\Modules\Notifications\Models\Notification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListNotifications
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Notification>
     */
    public function execute(User $actor, array $filters): LengthAwarePaginator
    {
        $this->tenantContext->require();

        $query = Notification::query()
            ->where('recipient_user_id', $actor->id);

        $unreadOnly = $filters['unread_only'] ?? null;
        if ($unreadOnly === 1 || $unreadOnly === '1' || $unreadOnly === true || $unreadOnly === 'true') {
            $query->whereNull('read_at');
        }

        if (! empty($filters['type']) && is_string($filters['type'])) {
            $type = NotificationType::tryFrom($filters['type']);
            if ($type !== null) {
                $query->where('type', $type->value);
            }
        }

        if (! empty($filters['severity']) && is_string($filters['severity'])) {
            $severity = NotificationSeverity::tryFrom($filters['severity']);
            if ($severity !== null) {
                $query->where('severity', $severity->value);
            }
        }

        if (! empty($filters['created_from'])) {
            $query->where('created_at', '>=', (string) $filters['created_from']);
        }

        if (! empty($filters['created_to'])) {
            $query->where('created_at', '<=', (string) $filters['created_to']);
        }

        $perPage = (int) ($filters['per_page'] ?? 15);
        $perPage = max(1, min(100, $perPage));

        return $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', max(1, (int) ($filters['page'] ?? 1)));
    }
}
