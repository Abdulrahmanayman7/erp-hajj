<?php

namespace App\Modules\Platform\Actions;

use App\Core\Auth\Support\EmailNormalizer;
use App\Core\Auth\UserStatus;
use App\Core\Authorization\EffectivePlatformPermissions;
use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Models\PlatformRole;
use App\Core\Authorization\PermissionCatalogSynchronizer;
use App\Core\Authorization\ProvisionDefaultPlatformRoles;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Models\User;
use App\Modules\Platform\Exceptions\PlatformDomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * One-time public bootstrap of the first platform administrator.
 * Closes when any active platform user holds an active platform role.
 */
final class BootstrapPlatformAdministrator
{
    private const LOCK_KEY = 'platform:bootstrap-administrator';

    public function __construct(
        private readonly PermissionCatalogSynchronizer $catalogSynchronizer,
        private readonly ProvisionDefaultPlatformRoles $provisionDefaultPlatformRoles,
        private readonly EffectivePlatformPermissions $effectivePlatformPermissions,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array{name: string, email: string, password: string}  $data
     * @return array{user: User, available: bool}
     */
    public function execute(array $data, ?Request $request = null): array
    {
        $lock = Cache::lock(self::LOCK_KEY, 15);

        if (! $lock->get()) {
            throw PlatformDomainException::setupInProgress();
        }

        try {
            if (EffectivePlatformPermissions::platformAdministratorExists()) {
                throw PlatformDomainException::setupUnavailable();
            }

            $this->catalogSynchronizer->sync();
            $this->provisionDefaultPlatformRoles->execute();

            $email = EmailNormalizer::normalize($data['email']);

            $user = DB::transaction(function () use ($data, $email, $request): User {
                $role = PlatformRole::query()
                    ->where('code', PlatformRole::CODE_SUPER_ADMIN)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (EffectivePlatformPermissions::platformAdministratorExists()) {
                    throw PlatformDomainException::setupUnavailable();
                }

                if (User::query()->where('email', $email)->lockForUpdate()->exists()) {
                    throw PlatformDomainException::ownerEmailTaken();
                }

                $user = new User;
                $user->forceFill([
                    'name' => $data['name'],
                    'email' => $email,
                    'password' => Hash::make($data['password']),
                    'tenant_id' => null,
                    'status' => UserStatus::Active,
                ]);
                $user->save();

                DB::table('platform_user_roles')->insert([
                    'user_id' => $user->id,
                    'platform_role_id' => $role->id,
                    'assigned_by' => null,
                    'created_at' => now(),
                ]);

                $this->security->record(AuthorizationSecurityEvent::PLATFORM_ADMIN_BOOTSTRAPPED, [
                    'context_type' => 'platform',
                    'actor_type' => 'system',
                    'actor_label' => 'platform_setup',
                    'user_id' => $user->id,
                    'entity_type' => 'user',
                    'entity_id' => $user->id,
                    'entity_label' => $user->name,
                ], $request);

                return $user;
            });

            $this->effectivePlatformPermissions->forgetUser($user);

            return [
                'user' => $user->fresh()->load('platformRoles'),
                'available' => false,
            ];
        } finally {
            $lock->release();
        }
    }
}
