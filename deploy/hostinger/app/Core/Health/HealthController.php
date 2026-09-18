<?php

namespace App\Core\Health;

use App\Core\Shared\ApiResponse;
use Illuminate\Http\JsonResponse;

class HealthController
{
    public function __invoke(): JsonResponse
    {
        return ApiResponse::success(
            data: [
                'application' => config('app.name'),
                'version' => 'v1',
                'status' => 'ok',
                'timestamp' => now()->toIso8601String(),
            ],
            message: 'ERP Hajj API is running',
        );
    }
}
