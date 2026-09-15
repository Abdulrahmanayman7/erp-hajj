<?php

namespace App\Modules\Users\Actions;

use App\Core\Auth\AvatarGroup;
use App\Core\Auth\UserStatus;
use App\Core\Authorization\EffectivePermissions;
use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\GrantAuthority;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Authorization\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

final class CreateUser
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly GrantAuthority $grantAuthority,
        private readonly EffectivePermissions $effectivePermissions,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * Mailers that never deliver to a real inbox (local / test).
     *
     * @var list<string>
     */
    private const NON_DELIVERING_MAILERS = ['log', 'array'];

    /**
     * @param  array{name: string, email: string, role_ids?: list<int>, send_invite?: bool, temporary_password?: string|null, avatar_group?: string}  $data
     * @return array{user: User, password_provisioned: bool, invite_sent: bool, invite_code: string|null}
     */
    public function execute(User $actor, array $data, Request $request): array
    {
        $tenant = $this->tenantContext->require();
        $roleIds = array_values(array_unique(array_map('intval', $data['role_ids'] ?? [])));
        $sendInvite = $data['send_invite'] ?? true;
        $temporaryPassword = $data['temporary_password'] ?? null;
        $avatarGroup = AvatarGroup::tryFrom((string) ($data['avatar_group'] ?? AvatarGroup::Neutral->value))
            ?? AvatarGroup::Neutral;

        $roles = collect();
        if ($roleIds !== []) {
            $roles = Role::query()->whereIn('id', $roleIds)->get();
            if ($roles->count() !== count($roleIds)) {
                abort(404);
            }
            $this->grantAuthority->assertCanAssignRoles($actor, $roles);
        }

        $passwordProvisioned = false;
        $plainPassword = null;

        if ($sendInvite) {
            $plainPassword = Str::password(32);
        } elseif (is_string($temporaryPassword) && $temporaryPassword !== '') {
            $plainPassword = $temporaryPassword;
            $passwordProvisioned = true;
        } else {
            $plainPassword = Str::password(32);
            $passwordProvisioned = true;
        }

        $inviteSent = false;
        $inviteCode = null;

        $user = DB::transaction(function () use ($actor, $data, $tenant, $roles, $plainPassword, $request, $sendInvite, $avatarGroup): User {
            $user = new User;
            $user->forceFill([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($plainPassword),
                'tenant_id' => $tenant->id,
                'status' => UserStatus::Active,
                'avatar_group' => $avatarGroup,
            ]);
            $user->save();

            if ($roles->isNotEmpty()) {
                $sync = [];
                foreach ($roles as $role) {
                    $sync[$role->id] = [
                        'tenant_id' => $tenant->id,
                        'assigned_by' => $actor->id,
                        'created_at' => now(),
                    ];
                }
                $user->roles()->sync($sync);
            }

            $this->security->record(AuthorizationSecurityEvent::USER_CREATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'user_id' => $user->id,
                'role_ids' => $roles->pluck('id')->all(),
                'invite_requested' => (bool) $sendInvite,
            ], $request);

            if ($roles->isNotEmpty()) {
                $this->security->record(AuthorizationSecurityEvent::USER_ROLES_CHANGED, [
                    'tenant_id' => $tenant->id,
                    'actor_id' => $actor->id,
                    'user_id' => $user->id,
                    'before' => [],
                    'after' => $roles->pluck('code')->all(),
                ], $request);
            }

            return $user->load('roles');
        });

        if ($sendInvite) {
            $mailer = (string) config('mail.default');

            if (in_array($mailer, self::NON_DELIVERING_MAILERS, true)) {
                $inviteSent = false;
                $inviteCode = 'INVITE_MAILER_UNAVAILABLE';
                Log::warning('User invite skipped: mailer does not deliver to inboxes.', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'mailer' => $mailer,
                    'code' => $inviteCode,
                ]);
            } else {
                $status = Password::broker()->sendResetLink(['email' => $user->email]);
                $inviteSent = $status === Password::RESET_LINK_SENT;

                if (! $inviteSent) {
                    $inviteCode = 'INVITE_SEND_FAILED';
                    Log::warning('User invite reset link was not sent.', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'status' => $status,
                        'mailer' => $mailer,
                        'code' => $inviteCode,
                    ]);
                }
            }
        }

        $this->effectivePermissions->forgetUser($user);

        return [
            'user' => $user,
            'password_provisioned' => $passwordProvisioned,
            'invite_sent' => $inviteSent,
            'invite_code' => $inviteCode,
        ];
    }
}
