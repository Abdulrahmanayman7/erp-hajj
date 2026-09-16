<?php

namespace App\Modules\Settings\Support;

use App\Core\Tenancy\Models\Tenant;

/**
 * Effective typed tenant settings from Tenant columns (ADR-0016).
 * Future KV overlay is intentionally not implemented in MVP.
 */
final class TenantSettingsResolver
{
    public function __construct(
        private readonly TenantMailConfigurationResolver $mailConfigurationResolver,
    ) {}

    /**
     * @return array{
     *   general: array{name: string, contact_name: ?string, contact_email: ?string, contact_phone: ?string},
     *   regional: array{timezone: string, locale: string, locale_editable: false},
     *   technical: array{
     *     status: string,
     *     deliverable: bool,
     *     mail_mailer: ?string,
     *     mail_host: ?string,
     *     mail_port: ?int,
     *     mail_encryption: ?string,
     *     mail_username: ?string,
     *     mail_password_configured: bool,
     *     mail_from_address: ?string,
     *     mail_from_name: ?string
     *   }
     * }
     */
    public function resolve(Tenant $tenant): array
    {
        $timezone = trim((string) $tenant->timezone);
        if ($timezone === '') {
            $timezone = 'Asia/Riyadh';
        }

        $locale = trim((string) $tenant->locale);
        if ($locale === '') {
            $locale = 'ar';
        }

        $mailFromAddress = trim((string) ($tenant->mail_from_address ?? ''));
        $mailFromName = trim((string) ($tenant->mail_from_name ?? ''));

        $resolved = $this->mailConfigurationResolver->resolve($tenant);
        $host = trim((string) ($tenant->mail_host ?? ''));
        $username = trim((string) ($tenant->mail_username ?? ''));
        $mailer = trim((string) ($tenant->mail_mailer ?? ''));
        $encryption = trim((string) ($tenant->mail_encryption ?? ''));
        $passwordConfigured = is_string($tenant->mail_password) && $tenant->mail_password !== '';

        return [
            'general' => [
                'name' => (string) $tenant->name,
                'contact_name' => $tenant->contact_name,
                'contact_email' => $tenant->contact_email,
                'contact_phone' => $tenant->contact_phone,
            ],
            'regional' => [
                'timezone' => $timezone,
                'locale' => $locale,
                'locale_editable' => false,
            ],
            'technical' => [
                'status' => $resolved->status,
                'deliverable' => $resolved->deliverable,
                'mail_mailer' => $mailer === '' ? null : strtolower($mailer),
                'mail_host' => $host === '' ? null : $host,
                'mail_port' => $tenant->mail_port !== null ? (int) $tenant->mail_port : null,
                'mail_encryption' => $encryption === '' ? null : strtolower($encryption),
                'mail_username' => $username === '' ? null : $username,
                'mail_password_configured' => $passwordConfigured,
                'mail_from_address' => $mailFromAddress === '' ? null : $mailFromAddress,
                'mail_from_name' => $mailFromName === '' ? null : $mailFromName,
            ],
        ];
    }
}
