<?php

namespace App\Providers;

use App\Core\Audit\Listeners\PersistAuthorizationSecurityAudit;
use App\Core\Audit\Listeners\PersistAuthSecurityAudit;
use App\Core\Auth\Events\AuthSecurityEvent;
use App\Core\Auth\Listeners\LogAuthSecurityEvent;
use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Listeners\LogAuthorizationSecurityEvent;
use App\Core\Shared\CorrelationId;
use App\Models\User;
use App\Modules\Assets\Models\Asset;
use App\Modules\Assets\Models\AssetCategory;
use App\Modules\Assets\Models\AssetCustody;
use App\Modules\Assets\Policies\AssetCategoryPolicy;
use App\Modules\Assets\Policies\AssetCustodyPolicy;
use App\Modules\Assets\Policies\AssetPolicy;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Audit\Policies\AuditLogPolicy;
use App\Modules\Authorization\Models\Role;
use App\Modules\Authorization\Policies\RolePolicy;
use App\Modules\Contracts\Models\Contract;
use App\Modules\Contracts\Models\ContractCategory;
use App\Modules\Contracts\Policies\ContractCategoryPolicy;
use App\Modules\Contracts\Policies\ContractPolicy;
use App\Modules\Dashboard\Policies\DashboardPolicy;
use App\Modules\Decisions\Models\Decision;
use App\Modules\Decisions\Policies\DecisionPolicy;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentCategory;
use App\Modules\Documents\Policies\DocumentCategoryPolicy;
use App\Modules\Documents\Policies\DocumentPolicy;
use App\Modules\Employees\Models\Employee;
use App\Modules\Employees\Models\Position;
use App\Modules\Employees\Policies\EmployeePolicy;
use App\Modules\Employees\Policies\PositionPolicy;
use App\Modules\Inventory\Models\InventoryBalance;
use App\Modules\Inventory\Models\InventoryCategory;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Policies\InventoryCategoryPolicy;
use App\Modules\Inventory\Policies\InventoryItemPolicy;
use App\Modules\Inventory\Policies\InventoryStockPolicy;
use App\Modules\Inventory\Policies\WarehousePolicy;
use App\Modules\Meetings\Models\Meeting;
use App\Modules\Meetings\Policies\MeetingPolicy;
use App\Modules\Notifications\Models\Notification;
use App\Modules\Notifications\Policies\NotificationPolicy;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use App\Modules\OrganizationStructure\Policies\OrganizationUnitPolicy;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Policies\TaskPolicy;
use App\Modules\Users\Policies\UserPolicy;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Eloquent\Relations\Relation;
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
        Event::listen(AuthSecurityEvent::class, PersistAuthSecurityAudit::class);
        Event::listen(AuthorizationSecurityEvent::class, LogAuthorizationSecurityEvent::class);
        Event::listen(AuthorizationSecurityEvent::class, PersistAuthorizationSecurityAudit::class);

        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(OrganizationUnit::class, OrganizationUnitPolicy::class);
        Gate::policy(Employee::class, EmployeePolicy::class);
        Gate::policy(Position::class, PositionPolicy::class);
        Gate::policy(Contract::class, ContractPolicy::class);
        Gate::policy(ContractCategory::class, ContractCategoryPolicy::class);
        Gate::policy(Meeting::class, MeetingPolicy::class);
        Gate::policy(Decision::class, DecisionPolicy::class);
        Gate::policy(Task::class, TaskPolicy::class);
        Gate::policy(Document::class, DocumentPolicy::class);
        Gate::policy(DocumentCategory::class, DocumentCategoryPolicy::class);
        Gate::policy(Warehouse::class, WarehousePolicy::class);
        Gate::policy(InventoryCategory::class, InventoryCategoryPolicy::class);
        Gate::policy(InventoryItem::class, InventoryItemPolicy::class);
        Gate::policy(InventoryBalance::class, InventoryStockPolicy::class);
        Gate::policy(Asset::class, AssetPolicy::class);
        Gate::policy(AssetCategory::class, AssetCategoryPolicy::class);
        Gate::policy(AssetCustody::class, AssetCustodyPolicy::class);
        Gate::policy(Notification::class, NotificationPolicy::class);
        Gate::policy(AuditLog::class, AuditLogPolicy::class);

        Gate::define('viewDashboard', [DashboardPolicy::class, 'view']);

        Relation::enforceMorphMap([
            'contract' => Contract::class,
            'meeting' => Meeting::class,
            'decision' => Decision::class,
            'task' => Task::class,
            'employee' => Employee::class,
            'organization_unit' => OrganizationUnit::class,
            'warehouse' => Warehouse::class,
            'inventory_item' => InventoryItem::class,
            'asset' => Asset::class,
            'custody' => AssetCustody::class,
        ]);

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

        // Recipient-owned: other users / cross-tenant → 404 (no existence leak).
        Route::bind('notification', function (string $value): Notification {
            $actor = auth()->user();

            if ($actor === null || $actor->tenant_id === null) {
                abort(404);
            }

            return Notification::query()
                ->whereKey($value)
                ->where('recipient_user_id', $actor->id)
                ->firstOrFail();
        });

        // Tenant audit rows only — foreign / platform-null → 404.
        Route::bind('auditLog', function (string $value): AuditLog {
            $actor = auth()->user();

            if ($actor === null || $actor->tenant_id === null) {
                abort(404);
            }

            return AuditLog::query()
                ->whereKey($value)
                ->where('tenant_id', $actor->tenant_id)
                ->firstOrFail();
        });
    }
}
