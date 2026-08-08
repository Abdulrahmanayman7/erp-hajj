<?php

namespace App\Modules\Users\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Models\User;
use Illuminate\Http\Request;

final class UpdateUser
{
    public function __construct(private readonly AuthorizationSecurity $security) {}

    /**
     * @param  array{name?: string, email?: string}  $data
     */
    public function execute(User $actor, User $user, array $data, Request $request): User
    {
        $before = [
            'name' => $user->name,
            'email' => $user->email,
        ];

        $user->fill(array_intersect_key($data, array_flip(['name', 'email'])));
        $user->save();

        $this->security->record(AuthorizationSecurityEvent::USER_UPDATED, [
            'tenant_id' => $user->tenant_id,
            'actor_id' => $actor->id,
            'user_id' => $user->id,
            'before' => $before,
            'after' => [
                'name' => $user->name,
                'email' => $user->email,
            ],
        ], $request);

        return $user->load('roles');
    }
}
