<?php

namespace App\Providers;

use App\Core\Auth\Events\AuthSecurityEvent;
use App\Core\Auth\Listeners\LogAuthSecurityEvent;
use App\Core\Shared\CorrelationId;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Event;
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
    }
}
