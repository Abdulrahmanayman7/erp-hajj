<?php

namespace App\Modules\OrganizationStructure\Resources;

use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrganizationUnit
 */
class OrganizationUnitResource extends JsonResource
{
    public bool $withChildren = false;

    /** @var array{children: list<mixed>, children_count: int, depth: int}|null */
    public ?array $treeMeta = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $unit = $this->resource;
        $depth = $this->treeMeta['depth'] ?? null;
        $childrenCount = $this->treeMeta['children_count']
            ?? ($unit->relationLoaded('children') ? $unit->children->count() : ($unit->children_count ?? null));

        $payload = [
            'id' => $unit->id,
            'name' => $unit->name,
            'code' => $unit->code,
            'type' => $unit->type->value,
            'status' => $unit->status->value,
            'parent_id' => $unit->parent_id,
            'sort_order' => $unit->sort_order,
            'manager' => $this->managerPayload($unit),
            'children_count' => $childrenCount ?? 0,
            'depth' => $depth,
            'created_at' => $unit->created_at?->toIso8601String(),
            'updated_at' => $unit->updated_at?->toIso8601String(),
        ];

        if ($this->withChildren && $this->treeMeta !== null) {
            $payload['children'] = array_map(
                function (array $childNode): array {
                    $resource = new self($childNode['unit']);
                    $resource->withChildren = true;
                    $resource->treeMeta = [
                        'children' => $childNode['children'],
                        'children_count' => $childNode['children_count'],
                        'depth' => $childNode['depth'],
                    ];

                    return $resource->resolve();
                },
                $this->treeMeta['children'],
            );
        }

        return $payload;
    }

    /**
     * @return array{id: int, name: string, email: string, status: string}|null
     */
    private function managerPayload(OrganizationUnit $unit): ?array
    {
        if (! $unit->relationLoaded('manager') || $unit->manager === null) {
            if ($unit->manager_user_id === null) {
                return null;
            }

            return null;
        }

        $manager = $unit->manager;

        return [
            'id' => $manager->id,
            'name' => $manager->name,
            'email' => $manager->email,
            'status' => $manager->status->value,
        ];
    }
}
