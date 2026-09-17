<?php

namespace App\Modules\Platform\Exceptions;

use App\Core\Shared\ApiResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class PlatformDomainException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly string $errorCode,
        private readonly int $httpStatus = 422,
    ) {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function status(): int
    {
        return $this->httpStatus;
    }

    public function render(): JsonResponse
    {
        return ApiResponse::error(
            message: $this->getMessage(),
            code: $this->errorCode(),
            status: $this->status(),
        );
    }

    public static function setupUnavailable(): self
    {
        return new self(
            'إعداد مدير المنصة غير متاح. يوجد بالفعل مدير منصة فعّال.',
            'PLATFORM_SETUP_UNAVAILABLE',
            403,
        );
    }

    public static function setupInProgress(): self
    {
        return new self(
            'جاري إعداد مدير المنصة. أعد المحاولة بعد لحظات.',
            'PLATFORM_SETUP_IN_PROGRESS',
            409,
        );
    }

    public static function invalidTenantTransition(): self
    {
        return new self(
            'انتقال حالة المنشأة غير مسموح.',
            'INVALID_TENANT_TRANSITION',
            422,
        );
    }

    public static function tenantCodeTaken(): self
    {
        return new self(
            'رمز المنشأة مستخدم مسبقاً.',
            'TENANT_CODE_TAKEN',
            422,
        );
    }

    public static function tenantNameTaken(): self
    {
        return new self(
            'اسم المنشأة مستخدم مسبقاً.',
            'TENANT_NAME_TAKEN',
            422,
        );
    }

    public static function ownerEmailTaken(): self
    {
        return new self(
            'البريد الإلكتروني لمالك المنشأة مستخدم مسبقاً.',
            'USER_EMAIL_TAKEN',
            422,
        );
    }

    public static function ownershipTargetInvalid(string $message = 'مالك المنشأة الجديد غير صالح.'): self
    {
        return new self($message, 'TENANT_OWNERSHIP_TARGET_INVALID', 422);
    }

    public static function ownershipSameUser(): self
    {
        return new self(
            'المالك الجديد هو نفس المالك الحالي.',
            'TENANT_OWNERSHIP_UNCHANGED',
            422,
        );
    }
}
