<?php

namespace App\Modules\OrganizationStructure\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\OrganizationStructure\Enums\OrganizationUnitStatus;
use App\Modules\OrganizationStructure\Enums\OrganizationUnitType;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use App\Modules\OrganizationStructure\Support\OrganizationHierarchy;
use App\Modules\OrganizationStructure\Support\OrganizationUnitValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CreateOrganizationUnit
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly OrganizationUnitValidator $validator,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array{
     *     name: string,
     *     code: string,
     *     type: string,
     *     parent_id?: int|null,
     *     manager_user_id?: int|null,
     *     sort_order?: int|null,
     *     status?: string|null
     * }  $data
     */
    public function execute(User $actor, array $data, Request $request): OrganizationUnit
    {
        $tenant = $this->tenantContext->require();
        $parentId = array_key_exists('parent_id', $data) && $data['parent_id'] !== null
            ? (int) $data['parent_id']
            : null;
        $managerId = array_key_exists('manager_user_id', $data) && $data['manager_user_id'] !== null
            ? (int) $data['manager_user_id']
            : null;

        return DB::transaction(function () use ($actor, $data, $request, $tenant, $parentId, $managerId): OrganizationUnit {
            $parent = $this->validator->resolveActiveParent($parentId);
            $depth = OrganizationHierarchy::depthForParent($parent);
            OrganizationHierarchy::assertDepthAllowed($depth);

            $this->validator->assertSiblingNameAvailable($data['name'], $parentId);
            $manager = $this->validator->resolveAssignableManager($managerId);

            $status = isset($data['status'])
                ? OrganizationUnitStatus::from($data['status'])
                : OrganizationUnitStatus::Active;

            $unit = OrganizationUnit::query()->create([
                'parent_id' => $parentId,
                'name' => $data['name'],
                'code' => $data['code'],
                'type' => OrganizationUnitType::from($data['type']),
                'status' => $status,
                'manager_user_id' => $manager?->id,
                'sort_order' => (int) ($data['sort_order'] ?? 0),
            ]);

            $this->security->record(AuthorizationSecurityEvent::ORGANIZATION_UNIT_CREATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'organization_unit_id' => $unit->id,
                'code' => $unit->code,
                'parent_id' => $unit->parent_id,
            ], $request);

            if ($manager !== null) {
                $this->security->record(AuthorizationSecurityEvent::ORGANIZATION_MANAGER_ASSIGNED, [
                    'tenant_id' => $tenant->id,
                    'actor_id' => $actor->id,
                    'organization_unit_id' => $unit->id,
                    'old_manager_user_id' => null,
                    'new_manager_user_id' => $manager->id,
                ], $request);
            }

            return $unit->load('manager')->loadCount('children');
        });
    }
}
