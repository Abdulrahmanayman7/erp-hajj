<?php

namespace App\Modules\OrganizationStructure\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\OrganizationStructure\Enums\OrganizationUnitType;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use App\Modules\OrganizationStructure\Support\OrganizationUnitValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class UpdateOrganizationUnit
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly OrganizationUnitValidator $validator,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array{
     *     name?: string,
     *     type?: string,
     *     manager_user_id?: int|null,
     *     sort_order?: int
     * }  $data
     */
    public function execute(User $actor, OrganizationUnit $unit, array $data, Request $request): OrganizationUnit
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $unit, $data, $request, $tenant): OrganizationUnit {
            $before = [
                'name' => $unit->name,
                'type' => $unit->type->value,
                'manager_user_id' => $unit->manager_user_id,
                'sort_order' => $unit->sort_order,
            ];

            if (isset($data['name']) && $data['name'] !== $unit->name) {
                $this->validator->assertSiblingNameAvailable($data['name'], $unit->parent_id, $unit->id);
                $unit->name = $data['name'];
            }

            if (isset($data['type'])) {
                $unit->type = OrganizationUnitType::from($data['type']);
            }

            if (array_key_exists('sort_order', $data)) {
                $unit->sort_order = (int) $data['sort_order'];
            }

            $oldManagerId = $unit->manager_user_id;
            $managerChanged = false;

            if (array_key_exists('manager_user_id', $data)) {
                $newManagerId = $data['manager_user_id'] !== null ? (int) $data['manager_user_id'] : null;
                if ($newManagerId !== $oldManagerId) {
                    $manager = $this->validator->resolveAssignableManager($newManagerId);
                    $unit->manager_user_id = $manager?->id;
                    $managerChanged = true;
                }
            }

            $unit->save();

            $this->security->record(AuthorizationSecurityEvent::ORGANIZATION_UNIT_UPDATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'organization_unit_id' => $unit->id,
                'before' => $before,
                'after' => [
                    'name' => $unit->name,
                    'type' => $unit->type->value,
                    'manager_user_id' => $unit->manager_user_id,
                    'sort_order' => $unit->sort_order,
                ],
            ], $request);

            if ($managerChanged) {
                $this->security->record(AuthorizationSecurityEvent::ORGANIZATION_MANAGER_ASSIGNED, [
                    'tenant_id' => $tenant->id,
                    'actor_id' => $actor->id,
                    'organization_unit_id' => $unit->id,
                    'old_manager_user_id' => $oldManagerId,
                    'new_manager_user_id' => $unit->manager_user_id,
                ], $request);
            }

            return $unit->fresh(['manager'])->loadCount('children');
        });
    }
}
