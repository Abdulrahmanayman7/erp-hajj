<?php

namespace App\Modules\OrganizationStructure\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\OrganizationStructure\Exceptions\OrganizationDomainException;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use App\Modules\OrganizationStructure\Support\OrganizationHierarchy;
use App\Modules\OrganizationStructure\Support\OrganizationUnitValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class MoveOrganizationUnit
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly OrganizationUnitValidator $validator,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, OrganizationUnit $unit, ?int $newParentId, Request $request): OrganizationUnit
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $unit, $newParentId, $request, $tenant): OrganizationUnit {
            $locked = OrganizationUnit::query()->whereKey($unit->id)->lockForUpdate()->firstOrFail();
            $oldParentId = $locked->parent_id;

            if ($newParentId !== null && (int) $newParentId === (int) $locked->id) {
                throw OrganizationDomainException::circularReference();
            }

            $allUnits = OrganizationUnit::query()->lockForUpdate()->get();

            if (OrganizationHierarchy::wouldCreateCycle($locked, $newParentId, $allUnits)) {
                throw OrganizationDomainException::circularReference();
            }

            $parent = $this->validator->resolveActiveParent($newParentId);
            $newDepth = OrganizationHierarchy::depthForParent($parent);
            $subtreeHeight = OrganizationHierarchy::subtreeHeight($locked, $allUnits);
            OrganizationHierarchy::assertDepthAllowed($newDepth + $subtreeHeight);

            if ($locked->name !== null) {
                $this->validator->assertSiblingNameAvailable($locked->name, $newParentId, $locked->id);
            }

            $locked->parent_id = $newParentId;
            $locked->save();

            $this->security->record(AuthorizationSecurityEvent::ORGANIZATION_UNIT_MOVED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'organization_unit_id' => $locked->id,
                'old_parent_id' => $oldParentId,
                'new_parent_id' => $newParentId,
            ], $request);

            return $locked->fresh(['manager'])->loadCount('children');
        });
    }
}
