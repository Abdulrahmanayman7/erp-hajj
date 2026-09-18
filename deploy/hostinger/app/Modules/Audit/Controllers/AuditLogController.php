<?php

namespace App\Modules\Audit\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\Audit\Actions\ListAuditLogs;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Audit\Requests\ListAuditLogsRequest;
use App\Modules\Audit\Resources\AuditLogResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController
{
    use AuthorizesRequests;

    public function index(ListAuditLogsRequest $request, ListAuditLogs $action): JsonResponse
    {
        $this->authorize('viewAny', AuditLog::class);

        $paginator = $action->execute($request->validated());

        return ApiResponse::success(
            data: AuditLogResource::collection($paginator->getCollection())->resolve(),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        );
    }

    public function show(Request $request, AuditLog $auditLog): JsonResponse
    {
        // Route binding already scoped to tenant; Policy double-checks permission + tenant.
        $this->authorize('view', $auditLog);

        $resource = new AuditLogResource($auditLog);
        $resource->withDetails = true;

        return ApiResponse::success(
            data: $resource->resolve(),
        );
    }
}
