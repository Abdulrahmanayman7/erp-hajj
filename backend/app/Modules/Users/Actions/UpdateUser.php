<?php

namespace App\Modules\Users\Actions;

use App\Core\Auth\AvatarGroup;
use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Models\User;
use Illuminate\Http\Request;

final class UpdateUser
{
    public function __construct(private readonly AuthorizationSecurity $security) {}

    /**
     * @param  array{name?: string, email?: string, avatar_group?: string}  $data
     */
    public function execute(User $actor, User $user, array $data, Request $request): User
    {
        $before = [
            'name' => $user->name,
            'email' => $user->email,
            'avatar_group' => $user->avatar_group instanceof AvatarGroup
                ? $user->avatar_group->value
                : AvatarGroup::Neutral->value,
        ];

        $fillable = array_intersect_key($data, array_flip(['name', 'email']));
        if ($fillable !== []) {
            $user->fill($fillable);
        }

        if (array_key_exists('avatar_group', $data)) {
            $user->forceFill([
                'avatar_group' => AvatarGroup::from((string) $data['avatar_group']),
            ]);
        }

        $user->save();

        $this->security->record(AuthorizationSecurityEvent::USER_UPDATED, [
            'tenant_id' => $user->tenant_id,
            'actor_id' => $actor->id,
            'user_id' => $user->id,
            'before' => $before,
            'after' => [
                'name' => $user->name,
                'email' => $user->email,
                'avatar_group' => $user->avatar_group instanceof AvatarGroup
                    ? $user->avatar_group->value
                    : AvatarGroup::Neutral->value,
            ],
        ], $request);

        return $user->load('roles');
    }
}
