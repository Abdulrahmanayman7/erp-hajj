<?php

namespace App\Core\Auth\Actions;

use App\Core\Auth\Events\AuthSecurityEvent;
use App\Core\Auth\Support\AuthSecurity;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LogoutUser
{
    public function __construct(private readonly AuthSecurity $security) {}

    public function execute(Request $request): void
    {
        /** @var User|null $user */
        $user = $request->user();

        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        Auth::forgetGuards();

        if ($user !== null) {
            $this->security->record(AuthSecurityEvent::LOGOUT, $request, [
                'user_id' => $user->id,
                'tenant_id' => $user->tenant_id,
                'context_type' => $user->isPlatformUser() ? 'platform' : 'tenant',
            ]);
        }
    }
}
