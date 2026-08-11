<?php

namespace App\Modules\OrganizationStructure\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\OrganizationStructure\Enums\OrganizationUnitStatus;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use Illuminate\Http\Request;

final class ActivateOrganizationUnit
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, OrganizationUnit $unit, Request $request): OrganizationUnit
    {
        if ($unit->status === OrganizationUnitStatus::Active) {
            return $unit->load('manager')->loadCount('children');
        }

        $tenant = $this->tenantContext->require();
        $unit->status = OrganizationUnitStatus::Active;
        $unit->save();

        $this->security->record(AuthorizationSecurityEvent::ORGANIZATION_UNIT_ACTIVATED, [
            'tenant_id' => $tenant->id,
            'actor_id' => $actor->id,
            'organization_unit_id' => $unit->id,
            'code' => $unit->code,
        ], $request);

        return $unit->fresh(['manager'])->loadCount('children');
    }
}
