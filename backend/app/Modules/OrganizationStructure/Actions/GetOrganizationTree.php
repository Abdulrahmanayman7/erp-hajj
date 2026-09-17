<?php

namespace App\Modules\OrganizationStructure\Actions;

use App\Core\Auth\UserStatus;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Authorization\Models\Role;
use App\Modules\Employees\Models\Employee;
use App\Modules\Employees\Models\Position;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use Illuminate\Support\Collection;

/**
 * Read-only projection of tenant + ownership + nested org units with
 * positions/employees assembled in memory (no N+1).
 */
final class GetOrganizationTree
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    /**
     * @return array{
     *   tenant: array{id: int, name: string, code: string, status: string},
     *   ownership: array{owner: array{id: int, name: string, email: string, status: string}|null},
     *   organization: array{units: list<array<string, mixed>>}
     * }
     */
    public function execute(): array
    {
        $tenant = $this->tenantContext->require();

        $units = OrganizationUnit::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $employees = Employee::query()
            ->orderBy('full_name')
            ->get([
                'id',
                'full_name',
                'organization_unit_id',
                'position_id',
                'supervisor_id',
                'status',
                'employee_number',
            ]);

        $positionIds = $employees
            ->pluck('position_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        /** @var Collection<int, Position> $positionsById */
        $positionsById = $positionIds === []
            ? collect()
            : Position::query()->whereIn('id', $positionIds)->get()->keyBy('id');

        $employeesByUnit = $employees->groupBy(
            fn (Employee $e): int => (int) $e->organization_unit_id,
        );

        $owner = User::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', UserStatus::Active)
            ->whereHas('roles', function ($query): void {
                $query->where('roles.code', Role::CODE_TENANT_OWNER)
                    ->where('roles.is_active', true);
            })
            ->orderBy('id')
            ->first(['id', 'name', 'email', 'status']);

        $nodes = [];
        foreach ($units as $unit) {
            $unitEmployees = $employeesByUnit->get($unit->id, collect());

            $unitPositionIds = $unitEmployees
                ->pluck('position_id')
                ->filter()
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values()
                ->all();

            $unitPositions = [];
            foreach ($unitPositionIds as $positionId) {
                $position = $positionsById->get($positionId);
                if ($position === null) {
                    continue;
                }
                $unitPositions[] = [
                    'id' => $position->id,
                    'name' => $position->name,
                    'code' => $position->code,
                    'is_active' => $position->is_active,
                ];
            }

            $nodes[$unit->id] = [
                'id' => $unit->id,
                'name' => $unit->name,
                'code' => $unit->code,
                'type' => $unit->type->value,
                'status' => $unit->status->value,
                'parent_id' => $unit->parent_id,
                'sort_order' => $unit->sort_order,
                'manager_user_id' => $unit->manager_user_id,
                'positions' => $unitPositions,
                'employees' => $unitEmployees->map(fn (Employee $e): array => [
                    'id' => $e->id,
                    'name' => $e->full_name,
                    'employee_number' => $e->employee_number,
                    'status' => $e->status->value,
                    'position_id' => $e->position_id,
                    'supervisor_employee_id' => $e->supervisor_id,
                ])->values()->all(),
                'children' => [],
            ];
        }

        $roots = [];
        foreach ($nodes as $id => &$node) {
            $parentId = $node['parent_id'];
            if ($parentId !== null && isset($nodes[$parentId])) {
                $nodes[$parentId]['children'][] = &$node;
            } else {
                $roots[] = &$node;
            }
        }
        unset($node);

        $sortRecursive = function (array &$list) use (&$sortRecursive): void {
            usort($list, function (array $a, array $b): int {
                $order = $a['sort_order'] <=> $b['sort_order'];
                if ($order !== 0) {
                    return $order;
                }

                return strcmp($a['name'], $b['name']);
            });
            foreach ($list as &$item) {
                $sortRecursive($item['children']);
            }
            unset($item);
        };
        $sortRecursive($roots);

        return [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'code' => $tenant->tenant_code,
                'status' => $tenant->status->value,
            ],
            'ownership' => [
                'owner' => $owner === null ? null : [
                    'id' => $owner->id,
                    'name' => $owner->name,
                    'email' => $owner->email,
                    'status' => $owner->status->value,
                ],
            ],
            'organization' => [
                'units' => $roots,
            ],
        ];
    }
}
