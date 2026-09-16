<?php

namespace App\Modules\Settings\Support;

use App\Core\Tenancy\Models\Tenant;

/**
 * Resolves effective From identity for tenant outbound mail.
 * SMTP transport stays global (env); only envelope From is tenant-scoped.
 */
final class TenantMailSenderResolver
{
    /**
     * @return array{address: string, name: string}
     */
    public function resolve(?Tenant $tenant): array
    {
        $fallbackAddress = trim((string) config('mail.from.address', ''));
        $fallbackName = trim((string) config('mail.from.name', ''));

        $address = $fallbackAddress;
        $name = $fallbackName;

        if ($tenant !== null) {
            $tenantAddress = trim((string) ($tenant->mail_from_address ?? ''));
            if ($tenantAddress !== '') {
                $address = $tenantAddress;
            }

            $tenantName = trim((string) ($tenant->mail_from_name ?? ''));
            if ($tenantName !== '') {
                $name = $tenantName;
            }
        }

        return [
            'address' => $address,
            'name' => $name,
        ];
    }

    /**
     * @return array{address: string, name: string}
     */
    public function resolveForTenantId(?int $tenantId): array
    {
        if ($tenantId === null) {
            return $this->resolve(null);
        }

        $tenant = Tenant::query()->find($tenantId);

        return $this->resolve($tenant);
    }
}
