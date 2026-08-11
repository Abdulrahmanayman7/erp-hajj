<?php

namespace App\Modules\Contracts\Exceptions;

use App\Core\Shared\ApiResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class ContractDomainException extends RuntimeException
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

    public static function numberTaken(): self
    {
        return new self('رقم العقد مستخدم مسبقاً.', 'CONTRACT_NUMBER_TAKEN', 422);
    }

    public static function invalidDateRange(): self
    {
        return new self('نطاق تواريخ العقد غير صالح.', 'CONTRACT_INVALID_DATE_RANGE', 422);
    }

    public static function invalidStatusTransition(): self
    {
        return new self('انتقال حالة العقد غير مسموح.', 'CONTRACT_INVALID_STATUS_TRANSITION', 422);
    }

    public static function commentRequired(): self
    {
        return new self('التعليق مطلوب لهذا الإجراء.', 'CONTRACT_COMMENT_REQUIRED', 422);
    }

    public static function employeeInvalid(): self
    {
        return new self('الموظف المرتبط غير صالح أو غير نشط.', 'CONTRACT_EMPLOYEE_INVALID', 422);
    }

    public static function organizationInvalid(): self
    {
        return new self('الوحدة التنظيمية غير صالحة أو غير نشطة.', 'CONTRACT_ORGANIZATION_INVALID', 422);
    }

    public static function categoryInvalid(): self
    {
        return new self('تصنيف العقد غير صالح أو غير نشط.', 'CONTRACT_CATEGORY_INVALID', 422);
    }

    public static function notEditable(): self
    {
        return new self('لا يمكن تعديل العقد إلا وهو في حالة مسودة.', 'CONTRACT_NOT_EDITABLE', 422);
    }

    public static function deleteForbidden(): self
    {
        return new self('لا يمكن حذف هذا العقد.', 'CONTRACT_DELETE_FORBIDDEN', 422);
    }

    public static function alreadyRenewed(): self
    {
        return new self('تم تجديد هذا العقد مسبقاً.', 'CONTRACT_ALREADY_RENEWED', 422);
    }

    public static function categoryInUse(): self
    {
        return new self('لا يمكن حذف تصنيف مرتبط بعقود. عطّله بدلاً من الحذف.', 'CONTRACT_CATEGORY_IN_USE', 422);
    }

    public static function categoryCodeTaken(): self
    {
        return new self('رمز التصنيف مستخدم مسبقاً.', 'CONTRACT_CATEGORY_CODE_TAKEN', 422);
    }

    public static function categoryNameTaken(): self
    {
        return new self('اسم التصنيف مستخدم مسبقاً.', 'CONTRACT_CATEGORY_NAME_TAKEN', 422);
    }

    public static function categoryCodeImmutable(): self
    {
        return new self('رمز التصنيف غير قابل للتعديل بعد الإنشاء.', 'CONTRACT_CATEGORY_CODE_IMMUTABLE', 422);
    }
}
