<?php

namespace App\Modules\Settings\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Settings\Exceptions\SettingsDomainException;
use App\Modules\Settings\Mail\TenantMailer;
use App\Modules\Settings\Support\TenantMailConfigurationResolver;
use Illuminate\Http\Request;

final class SendTenantTestEmail
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly TenantMailConfigurationResolver $resolver,
        private readonly TenantMailer $tenantMailer,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @return array{ok: bool, message: string, error_code: ?string}
     */
    public function execute(User $actor, string $email, Request $request): array
    {
        $tenant = $this->tenantContext->require();
        $resolved = $this->resolver->resolve($tenant);

        if (! $resolved->deliverable || ! $resolved->usesTenantSmtp()) {
            throw SettingsDomainException::mailTestRequiresTenantSmtp();
        }

        $result = $this->tenantMailer->sendTestHtml(
            $resolved,
            $email,
            'اختبار إعدادات البريد الإلكتروني',
            '<p dir="rtl">تم إعداد البريد الإلكتروني لمنشأتك بنجاح.</p>'
            .'<p dir="rtl">هذه رسالة تجريبية من نظام ERP Hajj.</p>',
        );

        if ($result['ok']) {
            $this->security->record(AuthorizationSecurityEvent::TENANT_EMAIL_TEST_SENT, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'tenant',
                'entity_id' => $tenant->id,
                'entity_number' => $tenant->tenant_code,
                'entity_label' => $tenant->name,
                'after_values' => [
                    'test_email_domain' => $this->emailDomain($email),
                    'mail_host' => $tenant->mail_host,
                ],
            ], $request);
        }

        return $result;
    }

    private function emailDomain(string $email): string
    {
        $parts = explode('@', $email, 2);

        return isset($parts[1]) ? '@'.$parts[1] : '@';
    }
}
