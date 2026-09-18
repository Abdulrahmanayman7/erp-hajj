<?php

namespace App\Modules\OrganizationStructure\Support;

use App\Modules\OrganizationStructure\Exceptions\OrganizationDomainException;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use Illuminate\Support\Collection;

/**
 * Hierarchy helpers for adjacency-list organization units.
 * Max depth is read from config('organization.max_depth') — never hardcode 8.
 */
final class OrganizationHierarchy
{
    public static function maxDepth(): int
    {
        return (int) config('organization.max_depth');
    }

    /**
     * Depth of a unit given its parent's depth (root parent → depth 0).
     */
    public static function depthForParent(?OrganizationUnit $parent): int
    {
        if ($parent === null) {
            return 0;
        }

        return self::computeDepth($parent) + 1;
    }

    public static function computeDepth(OrganizationUnit $unit): int
    {
        $depth = 0;
        $current = $unit;
        $guard = self::maxDepth() + 2;

        while ($current->parent_id !== null) {
            $depth++;
            if ($depth > $guard) {
                throw OrganizationDomainException::circularReference();
            }
            $parent = $current->relationLoaded('parent')
                ? $current->parent
                : OrganizationUnit::query()->find($current->parent_id);
            if ($parent === null) {
                break;
            }
            $current = $parent;
        }

        return $depth;
    }

    /**
     * @param  Collection<int, OrganizationUnit>  $allUnits
     * @return array<int, int> id => depth
     */
    public static function depthsForCollection(Collection $allUnits): array
    {
        $byId = $allUnits->keyBy('id');
        $depths = [];

        $resolve = function (OrganizationUnit $unit) use (&$resolve, &$depths, $byId): int {
            if (isset($depths[$unit->id])) {
                return $depths[$unit->id];
            }

            if ($unit->parent_id === null) {
                return $depths[$unit->id] = 0;
            }

            $parent = $byId->get($unit->parent_id);
            if ($parent === null) {
                return $depths[$unit->id] = 0;
            }

            return $depths[$unit->id] = $resolve($parent) + 1;
        };

        foreach ($allUnits as $unit) {
            $resolve($unit);
        }

        return $depths;
    }

    public static function assertDepthAllowed(int $depth): void
    {
        if ($depth > self::maxDepth()) {
            throw OrganizationDomainException::depthExceeded();
        }
    }

    /**
     * Whether $candidateParentId is the unit itself or a descendant of $unit.
     *
     * @param  Collection<int, OrganizationUnit>|null  $allUnits  optional preloaded set
     */
    public static function wouldCreateCycle(OrganizationUnit $unit, ?int $candidateParentId, ?Collection $allUnits = null): bool
    {
        if ($candidateParentId === null) {
            return false;
        }

        if ((int) $candidateParentId === (int) $unit->id) {
            return true;
        }

        if ($allUnits !== null) {
            $childrenByParent = $allUnits->groupBy(fn (OrganizationUnit $u) => $u->parent_id ?? 0);
            $stack = [$unit->id];
            $descendants = [];

            while ($stack !== []) {
                $id = array_pop($stack);
                $descendants[$id] = true;
                foreach ($childrenByParent->get($id, collect()) as $child) {
                    if (! isset($descendants[$child->id])) {
                        $stack[] = $child->id;
                    }
                }
            }

            return isset($descendants[$candidateParentId]);
        }

        $currentId = $candidateParentId;
        $guard = self::maxDepth() + 2;
        $seen = 0;

        while ($currentId !== null) {
            if ((int) $currentId === (int) $unit->id) {
                return true;
            }
            $seen++;
            if ($seen > $guard) {
                return true;
            }
            $currentId = OrganizationUnit::query()->whereKey($currentId)->value('parent_id');
        }

        return false;
    }

    /**
     * Relative depth of the deepest descendant under $unit (unit itself = 0).
     *
     * @param  Collection<int, OrganizationUnit>  $allUnits
     */
    public static function subtreeHeight(OrganizationUnit $unit, Collection $allUnits): int
    {
        $childrenByParent = $allUnits->groupBy(fn (OrganizationUnit $u) => $u->parent_id ?? 0);

        $walk = function (int $id) use (&$walk, $childrenByParent): int {
            $max = 0;
            foreach ($childrenByParent->get($id, collect()) as $child) {
                $max = max($max, 1 + $walk($child->id));
            }

            return $max;
        };

        return $walk($unit->id);
    }

    /**
     * Build nested tree arrays from a flat collection (sorted siblings).
     *
     * @param  Collection<int, OrganizationUnit>  $units
     * @param  array<int, int>  $depths
     * @return list<array{unit: OrganizationUnit, depth: int, children: list<mixed>, children_count: int}>
     */
    public static function buildTree(Collection $units, array $depths): array
    {
        $nodes = [];
        foreach ($units as $unit) {
            $nodes[$unit->id] = [
                'unit' => $unit,
                'depth' => $depths[$unit->id] ?? 0,
                'children' => [],
                'children_count' => 0,
            ];
        }

        $roots = [];
        foreach ($nodes as $id => &$node) {
            $parentId = $node['unit']->parent_id;
            if ($parentId !== null && isset($nodes[$parentId])) {
                $nodes[$parentId]['children'][] = &$node;
            } else {
                $roots[] = &$node;
            }
        }
        unset($node);

        $sortRecursive = function (array &$list) use (&$sortRecursive): void {
            usort($list, function (array $a, array $b): int {
                $order = $a['unit']->sort_order <=> $b['unit']->sort_order;
                if ($order !== 0) {
                    return $order;
                }

                return strcmp($a['unit']->name, $b['unit']->name);
            });
            foreach ($list as &$item) {
                $sortRecursive($item['children']);
                $item['children_count'] = count($item['children']);
            }
            unset($item);
        };

        $sortRecursive($roots);

        return $roots;
    }
}
