<?php

namespace App\Providers;

use App\Core\Auth\Events\AuthSecurityEvent;
use App\Core\Auth\Listeners\LogAuthSecurityEvent;
use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Listeners\LogAuthorizationSecurityEvent;
use App\Core\Shared\CorrelationId;
use App\Models\User;
use App\Modules\Authorization\Models\Role;
use App\Modules\Authorization\Policies\RolePolicy;
use App\Modules\Users\Policies\UserPolicy;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(CorrelationId::class);
    }

    public function boot(): void
    {
        Password::defaults(fn (): Password => Password::min(8)->letters()->numbers());

        ResetPassword::createUrlUsing(function (User $user, string $token): string {
            $frontend = rtrim((string) config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:5173')), '/');

            return $frontend.'/reset-password?'.http_build_query([
                'token' => $token,
                'email' => $user->email,
            ]);
        });

        Event::listen(AuthSecurityEvent::class, LogAuthSecurityEvent::class);
        Event::listen(AuthorizationSecurityEvent::class, LogAuthorizationSecurityEvent::class);

        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);

        Route::bind('user', function (string $value): User {
            $actor = auth()->user();

            if ($actor === null || $actor->tenant_id === null) {
                abort(404);
            }

            return User::query()
                ->whereKey($value)
                ->where('tenant_id', $actor->tenant_id)
                ->firstOrFail();
        });
    }
}
