<?php

namespace App\Modules\Settings\Support;

use App\Core\Tenancy\Models\Tenant;

/**
 * Effective typed tenant settings from Tenant columns (ADR-0016).
 * Future KV overlay is intentionally not implemented in MVP.
 */
final class TenantSettingsResolver
{
    /**
     * @return array{
     *   general: array{name: string, contact_name: ?string, contact_email: ?string, contact_phone: ?string},
     *   regional: array{timezone: string, locale: string, locale_editable: false},
     *   technical: array{mail_from_address: ?string, mail_from_name: ?string}
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
                'mail_from_address' => $mailFromAddress === '' ? null : $mailFromAddress,
                'mail_from_name' => $mailFromName === '' ? null : $mailFromName,
            ],
        ];
    }
}
