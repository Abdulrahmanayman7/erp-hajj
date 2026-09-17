<?php

namespace App\Modules\Platform\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\Platform\Actions\BootstrapPlatformAdministrator;
use App\Modules\Platform\Actions\GetPlatformSetupStatus;
use App\Modules\Platform\Exceptions\PlatformDomainException;
use App\Modules\Platform\Requests\BootstrapPlatformAdministratorRequest;
use Illuminate\Http\JsonResponse;

class PlatformSetupController
{
    public function status(GetPlatformSetupStatus $action): JsonResponse
    {
        return ApiResponse::success(data: $action->execute());
    }

    public function store(
        BootstrapPlatformAdministratorRequest $request,
        BootstrapPlatformAdministrator $action,
        GetPlatformSetupStatus $status,
    ): JsonResponse {
        if (! $status->execute()['available']) {
            throw PlatformDomainException::setupUnavailable();
        }

        $result = $action->execute($request->validatedPayload(), $request);

        return ApiResponse::success(
            data: [
                'available' => false,
                'user' => [
                    'id' => $result['user']->id,
                    'name' => $result['user']->name,
                    'email' => $result['user']->email,
                    'is_platform_user' => true,
                ],
            ],
            message: 'تم إنشاء مدير المنصة بنجاح.',
            status: 201,
        );
    }
}
