<?php

namespace App\Modules\Settings\Actions;

use App\Core\Tenancy\TenantContext;
use App\Modules\Settings\Support\TenantSettingsResolver;
use Illuminate\Http\Request;

final class GetTenantSettings
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly TenantSettingsResolver $resolver,
    ) {}

    /**
     * @return array{
     *   general: array{name: string, contact_name: ?string, contact_email: ?string, contact_phone: ?string},
     *   regional: array{timezone: string, locale: string, locale_editable: false}
     * }
     */
    public function execute(Request $request): array
    {
        unset($request);

        return $this->resolver->resolve($this->tenantContext->require());
    }
}
