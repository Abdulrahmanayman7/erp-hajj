<?php

namespace App\Modules\Authorization\Controllers;

use App\Core\Authorization\PermissionCatalog;
use App\Core\Shared\ApiResponse;
use App\Modules\Authorization\Models\Permission;
use App\Modules\Authorization\Models\Role;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PermissionController
{
    use AuthorizesRequests;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewPermissions', Role::class);

        $query = Permission::query()->orderBy('module')->orderBy('name');

        if ($request->filled('module')) {
            $query->where('module', $request->query('module'));
        }

        if ($request->filled('search')) {
            $like = '%'.$request->query('search').'%';
            $query->where(function ($q) use ($like): void {
                $q->where('name', 'like', $like)
                    ->orWhere('display_name', 'like', $like);
            });
        }

        $permissions = $query->get();

        $modules = $permissions
            ->groupBy('module')
            ->map(function ($items, string $module): array {
                return [
                    'module' => $module,
                    'display_name' => PermissionCatalog::MODULE_DISPLAY[$module] ?? $module,
                    'permissions' => $items->map(fn (Permission $p): array => [
                        'id' => $p->id,
                        'name' => $p->name,
                        'display_name' => $p->display_name,
                        'description' => $p->description,
                        'high_risk' => in_array($p->name, PermissionCatalog::highRiskPermissions(), true),
                    ])->values()->all(),
                ];
            })
            ->values()
            ->all();

        return ApiResponse::success(data: ['modules' => $modules]);
    }
}
