<?php

namespace App\Modules\Users\Actions;

use App\Core\Auth\UserStatus;
use App\Core\Authorization\EffectivePermissions;
use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Authorization\TenantOwnerGuard;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class DisableUser
{
    public function __construct(
        private readonly TenantOwnerGuard $ownerGuard,
        private readonly EffectivePermissions $effectivePermissions,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, User $user, Request $request, ?string $reason = null): User
    {
        if ($user->status === UserStatus::Disabled) {
            return $user->load('roles');
        }

        DB::transaction(function () use ($actor, $user, $request, $reason): void {
            User::query()->whereKey($user->id)->lockForUpdate()->first();
            $this->ownerGuard->assertCanDisable($actor, $user->fresh());

            $user->status = UserStatus::Disabled;
            $user->save();

            $this->security->record(AuthorizationSecurityEvent::USER_DISABLED, [
                'tenant_id' => $user->tenant_id,
                'actor_id' => $actor->id,
                'user_id' => $user->id,
                'reason' => $reason,
            ], $request);
        });

        $this->effectivePermissions->forgetUser($user);

        return $user->fresh()->load('roles');
    }
}
