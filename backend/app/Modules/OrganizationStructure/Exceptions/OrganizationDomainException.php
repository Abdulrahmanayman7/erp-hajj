<?php

namespace App\Modules\OrganizationStructure\Exceptions;

use App\Core\Shared\ApiResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class OrganizationDomainException extends RuntimeException
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

    public static function parentInvalid(): self
    {
        return new self('الوحدة الأب غير صالحة.', 'ORGANIZATION_UNIT_PARENT_INVALID', 422);
    }

    public static function parentInactive(): self
    {
        return new self('لا يمكن إنشاء أو نقل وحدة تحت وحدة أب غير نشطة.', 'ORGANIZATION_UNIT_PARENT_INACTIVE', 422);
    }

    public static function circularReference(): self
    {
        return new self('لا يمكن إنشاء مرجع دائري في الهيكل التنظيمي.', 'ORGANIZATION_UNIT_CIRCULAR_REFERENCE', 422);
    }

    public static function depthExceeded(): self
    {
        return new self('تم تجاوز الحد الأقصى لعمق الهيكل التنظيمي.', 'ORGANIZATION_UNIT_DEPTH_EXCEEDED', 422);
    }

    public static function hasChildren(): self
    {
        return new self('لا يمكن حذف وحدة تنظيمية تحتوي على وحدات فرعية. استخدم التعطيل بدلاً من الحذف.', 'ORGANIZATION_UNIT_HAS_CHILDREN', 422);
    }

    public static function inUse(): self
    {
        return new self('لا يمكن حذف وحدة مرتبطة بسجلات عمل. استخدم التعطيل بدلاً من الحذف.', 'ORGANIZATION_UNIT_IN_USE', 422);
    }

    public static function inactive(): self
    {
        return new self('الوحدة التنظيمية غير نشطة.', 'ORGANIZATION_UNIT_INACTIVE', 422);
    }

    public static function codeTaken(): self
    {
        return new self('رمز الوحدة التنظيمية مستخدم مسبقاً.', 'ORGANIZATION_UNIT_CODE_TAKEN', 422);
    }

    public static function nameTaken(): self
    {
        return new self('اسم الوحدة التنظيمية مستخدم تحت نفس الأب.', 'ORGANIZATION_UNIT_NAME_TAKEN', 422);
    }

    public static function codeImmutable(): self
    {
        return new self('رمز الوحدة التنظيمية غير قابل للتعديل بعد الإنشاء.', 'ORGANIZATION_UNIT_CODE_IMMUTABLE', 422);
    }

    public static function managerInvalid(): self
    {
        return new self('مسؤول الوحدة غير صالح أو غير نشط.', 'ORGANIZATION_MANAGER_INVALID', 422);
    }
}
