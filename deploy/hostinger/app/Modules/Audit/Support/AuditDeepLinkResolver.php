<?php

namespace App\Modules\Audit\Support;

use App\Models\User;
use App\Modules\Assets\Models\Asset;
use App\Modules\Assets\Models\AssetCustody;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Contracts\Models\Contract;
use App\Modules\Decisions\Models\Decision;
use App\Modules\Documents\Models\Document;
use App\Modules\Employees\Models\Employee;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Meetings\Models\Meeting;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use App\Modules\Tasks\Models\Task;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Server-side deep-link availability (never raw URLs from DB).
 */
final class AuditDeepLinkResolver
{
    /**
     * @return array{available: bool, entity_type: string, entity_id: int}|null
     */
    public function resolve(User $actor, AuditLog $log): ?array
    {
        if ($log->entity_type === null || $log->entity_id === null) {
            return null;
        }

        $type = $log->entity_type;
        $id = (int) $log->entity_id;

        $model = $this->findEntity($type, $id);
        if ($model === null) {
            return [
                'available' => false,
                'entity_type' => $type,
                'entity_id' => $id,
            ];
        }

        $allowed = $this->canView($actor, $type, $model);

        return [
            'available' => $allowed,
            'entity_type' => $type,
            'entity_id' => $id,
        ];
    }

    private function findEntity(string $type, int $id): ?Model
    {
        return match ($type) {
            'contract' => Contract::query()->find($id),
            'meeting' => Meeting::query()->find($id),
            'decision' => Decision::query()->find($id),
            'task' => Task::query()->find($id),
            'document' => Document::query()->find($id),
            'employee' => Employee::query()->find($id),
            'organization_unit' => OrganizationUnit::query()->find($id),
            'warehouse' => Warehouse::query()->find($id),
            'inventory_item' => InventoryItem::query()->find($id),
            'asset' => Asset::query()->find($id),
            'custody' => AssetCustody::query()->find($id),
            'user' => User::query()->whereKey($id)->where('tenant_id', auth()->user()?->tenant_id)->first(),
            default => null,
        };
    }

    private function canView(User $actor, string $type, Model $model): bool
    {
        return match ($type) {
            'contract' => Gate::forUser($actor)->allows('view', $model),
            'meeting' => Gate::forUser($actor)->allows('view', $model),
            'decision' => Gate::forUser($actor)->allows('view', $model),
            'task' => Gate::forUser($actor)->allows('view', $model),
            'document' => Gate::forUser($actor)->allows('view', $model),
            'employee' => Gate::forUser($actor)->allows('view', $model),
            'organization_unit' => Gate::forUser($actor)->allows('view', $model),
            'warehouse' => Gate::forUser($actor)->allows('view', $model),
            'inventory_item' => Gate::forUser($actor)->allows('view', $model),
            'asset' => Gate::forUser($actor)->allows('view', $model),
            'custody' => Gate::forUser($actor)->allows('view', $model),
            'user' => Gate::forUser($actor)->allows('view', $model),
            default => false,
        };
    }
}
