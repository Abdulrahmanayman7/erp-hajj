<?php

namespace App\Modules\Settings\Exceptions;

use App\Core\Shared\ApiResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class SettingsDomainException extends RuntimeException
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

    public static function unknownField(): self
    {
        return new self('حقل إعدادات غير معروف.', 'SETTINGS_UNKNOWN_FIELD', 422);
    }

    public static function immutableField(): self
    {
        return new self('لا يمكن تعديل هذا الحقل عبر إعدادات المنشأة.', 'SETTINGS_IMMUTABLE_FIELD', 422);
    }

    public static function invalidTimezone(): self
    {
        return new self('المنطقة الزمنية غير صالحة. استخدم معرف IANA فقط.', 'SETTINGS_INVALID_TIMEZONE', 422);
    }

    public static function nameTaken(): self
    {
        return new self('اسم المنشأة مستخدم مسبقاً.', 'SETTINGS_NAME_TAKEN', 422);
    }

    public static function noChanges(): self
    {
        return new self('لا توجد حقول قابلة للتحديث في الطلب.', 'SETTINGS_NO_CHANGES', 422);
    }
}
