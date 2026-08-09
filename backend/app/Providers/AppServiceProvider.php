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
use App\Modules\Employees\Models\Employee;
use App\Modules\Employees\Models\Position;
use App\Modules\Employees\Policies\EmployeePolicy;
use App\Modules\Employees\Policies\PositionPolicy;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use App\Modules\OrganizationStructure\Policies\OrganizationUnitPolicy;
use App\Modules\Users\Policies\UserPolicy;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
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

        ResetPassword::toMailUsing(function (User $user, string $token): MailMessage {
            $frontend = rtrim((string) config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:5173')), '/');
            $url = $frontend.'/reset-password?'.http_build_query([
                'token' => $token,
                'email' => $user->email,
            ]);

            return (new MailMessage)
                ->subject('تعيين كلمة المرور — رفيع')
                ->greeting('مرحبًا '.$user->name)
                ->line('تم إنشاء حسابك أو طلب إعادة تعيين كلمة المرور في منصة رفيع.')
                ->line('اضغط الزر أدناه لتعيين كلمة مرور جديدة.')
                ->action('تعيين كلمة المرور', $url)
                ->line('رابط التعيين صالح لمدة 60 دقيقة.')
                ->line('إذا لم تطلب ذلك، يمكنك تجاهل هذه الرسالة.')
                ->salutation('مع التحية، فريق رفيع');
        });

        Event::listen(AuthSecurityEvent::class, LogAuthSecurityEvent::class);
        Event::listen(AuthorizationSecurityEvent::class, LogAuthorizationSecurityEvent::class);

        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(OrganizationUnit::class, OrganizationUnitPolicy::class);
        Gate::policy(Employee::class, EmployeePolicy::class);
        Gate::policy(Position::class, PositionPolicy::class);

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
