<?php

namespace App\Core\Auth\Exceptions;

use App\Core\Shared\ApiResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

abstract class AuthException extends RuntimeException
{
    abstract public function errorCode(): string;

    abstract public function status(): int;

    public function render(): JsonResponse
    {
        return ApiResponse::error(
            message: $this->getMessage(),
            code: $this->errorCode(),
            status: $this->status(),
        );
    }
}
