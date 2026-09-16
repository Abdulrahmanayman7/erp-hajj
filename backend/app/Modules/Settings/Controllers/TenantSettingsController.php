<?php

namespace App\Modules\Settings\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\Settings\Actions\GetTenantSettings;
use App\Modules\Settings\Actions\SendTenantTestEmail;
use App\Modules\Settings\Actions\UpdateTenantSettings;
use App\Modules\Settings\Requests\SendTenantTestEmailRequest;
use App\Modules\Settings\Requests\UpdateTenantSettingsRequest;
use App\Modules\Settings\Resources\TenantSettingsResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantSettingsController
{
    use AuthorizesRequests;

    public function show(Request $request, GetTenantSettings $action): JsonResponse
    {
        $this->authorize('viewTenantSettings');

        return ApiResponse::success(
            data: TenantSettingsResource::make($action->execute($request)),
        );
    }

    public function update(
        UpdateTenantSettingsRequest $request,
        UpdateTenantSettings $action,
    ): JsonResponse {
        $this->authorize('updateTenantSettings');

        $payload = $action->execute(
            $request->user(),
            $request->settingsPayload(),
            $request,
        );

        return ApiResponse::success(
            data: TenantSettingsResource::make($payload),
            message: 'تم تحديث إعدادات المنشأة.',
        );
    }

    public function testEmail(
        SendTenantTestEmailRequest $request,
        SendTenantTestEmail $action,
    ): JsonResponse {
        $this->authorize('updateTenantSettings');

        $result = $action->execute(
            $request->user(),
            (string) $request->validated('email'),
            $request,
        );

        if (! $result['ok']) {
            return ApiResponse::error(
                message: $result['message'],
                code: $result['error_code'] ?? 'MAIL_SEND_FAILED',
                status: 422,
            );
        }

        return ApiResponse::success(
            data: ['sent' => true],
            message: $result['message'],
        );
    }
}
