<?php

namespace App\Modules\Users\Actions;

use App\Core\Auth\UserStatus;
use App\Core\Authorization\EffectivePermissions;
use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Models\User;
use Illuminate\Http\Request;

final class EnableUser
{
    public function __construct(
        private readonly EffectivePermissions $effectivePermissions,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, User $user, Request $request, ?string $reason = null): User
    {
        if ($user->status === UserStatus::Active) {
            return $user->load('roles');
        }

        $user->status = UserStatus::Active;
        $user->save();

        $this->security->record(AuthorizationSecurityEvent::USER_ENABLED, [
            'tenant_id' => $user->tenant_id,
            'actor_id' => $actor->id,
            'user_id' => $user->id,
            'reason' => $reason,
        ], $request);

        $this->effectivePermissions->forgetUser($user);

        return $user->fresh()->load('roles');
    }
}
