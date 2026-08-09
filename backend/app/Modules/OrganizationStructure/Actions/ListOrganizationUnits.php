<?php

namespace App\Modules\OrganizationStructure\Actions;

use App\Modules\OrganizationStructure\Enums\OrganizationUnitStatus;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use App\Modules\OrganizationStructure\Support\OrganizationHierarchy;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class ListOrganizationUnits
{
    /**
     * @param  array{
     *     view?: string,
     *     status?: string|null,
     *     search?: string|null,
     *     parent_id?: int|null,
     *     per_page?: int
     * }  $filters
     * @return array{mode: 'tree', roots: list<array{unit: OrganizationUnit, depth: int, children: list<mixed>, children_count: int>, depths: array<int, int>}|array{mode: 'flat', paginator: LengthAwarePaginator, depths: array<int, int>}|array{mode: 'flat_all', units: Collection<int, OrganizationUnit>, depths: array<int, int>}
     */
    public function execute(array $filters): array
    {
        $view = $filters['view'] ?? 'tree';
        $status = $filters['status'] ?? 'all';
        $search = isset($filters['search']) ? trim((string) $filters['search']) : '';
        $parentId = $filters['parent_id'] ?? null;

        $base = OrganizationUnit::query()->with('manager');

        if ($status === 'active' || $status === 'inactive') {
            $base->where('status', OrganizationUnitStatus::from($status));
        }

        if ($search !== '') {
            $base->where(function ($q) use ($search): void {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('code', 'like', '%'.$search.'%');
            });
        }

        if ($view === 'flat') {
            if ($parentId !== null) {
                $base->where('parent_id', $parentId);
            }

            $allForDepth = OrganizationUnit::query()->get(['id', 'parent_id']);
            $depths = OrganizationHierarchy::depthsForCollection($allForDepth);

            $perPage = max(1, min(100, (int) ($filters['per_page'] ?? 50)));
            $total = (clone $base)->count();

            // Typical tenant size ≤ ~500: return all without pagination when small.
            if ($total <= 500 && ! isset($filters['per_page'])) {
                $units = $base->orderBy('sort_order')->orderBy('name')->get();

                return [
                    'mode' => 'flat_all',
                    'units' => $units,
                    'depths' => $depths,
                ];
            }

            $paginator = $base->orderBy('sort_order')->orderBy('name')->paginate($perPage);

            return [
                'mode' => 'flat',
                'paginator' => $paginator,
                'depths' => $depths,
            ];
        }

        // Tree: load full tenant set, filter by status in memory, build hierarchy once.
        $units = OrganizationUnit::query()->with('manager')->get();
        if ($status === 'active' || $status === 'inactive') {
            $statusEnum = OrganizationUnitStatus::from($status);
            $units = $units->filter(fn (OrganizationUnit $u) => $u->status === $statusEnum)->values();
        }

        $depths = OrganizationHierarchy::depthsForCollection($units);

        if ($search !== '') {
            $matchIds = $units
                ->filter(fn (OrganizationUnit $u) => str_contains(mb_strtolower($u->name), mb_strtolower($search))
                    || str_contains(mb_strtolower($u->code), mb_strtolower($search)))
                ->pluck('id')
                ->all();

            $keep = [];
            $byId = $units->keyBy('id');
            foreach ($matchIds as $id) {
                $current = $byId->get($id);
                while ($current !== null) {
                    $keep[$current->id] = true;
                    $current = $current->parent_id !== null ? $byId->get($current->parent_id) : null;
                }
            }
            $units = $units->filter(fn (OrganizationUnit $u) => isset($keep[$u->id]))->values();
            $depths = OrganizationHierarchy::depthsForCollection($units);
        }

        $roots = OrganizationHierarchy::buildTree($units, $depths);

        return [
            'mode' => 'tree',
            'roots' => $roots,
            'depths' => $depths,
        ];
    }
}
