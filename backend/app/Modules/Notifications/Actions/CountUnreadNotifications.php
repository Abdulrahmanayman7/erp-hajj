<?php

namespace App\Modules\Notifications\Actions;

use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Notifications\Models\Notification;

final class CountUnreadNotifications
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function execute(User $actor): int
    {
        $this->tenantContext->require();

        return Notification::query()
            ->where('recipient_user_id', $actor->id)
            ->whereNull('read_at')
            ->count();
    }
}
