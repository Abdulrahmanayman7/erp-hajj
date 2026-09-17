<?php

namespace App\Modules\OrganizationStructure\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\OrganizationStructure\Actions\GetOrganizationTree;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;

class OrganizationTreeController
{
    use AuthorizesRequests;

    public function show(GetOrganizationTree $action): JsonResponse
    {
        $this->authorize('viewAny', OrganizationUnit::class);

        return ApiResponse::success(data: $action->execute());
    }
}
