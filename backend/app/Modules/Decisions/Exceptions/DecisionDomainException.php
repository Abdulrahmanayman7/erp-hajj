<?php

namespace App\Modules\Decisions\Exceptions;

use App\Core\Shared\ApiResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class DecisionDomainException extends RuntimeException
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
        return new self('رقم القرار مستخدم مسبقاً.', 'DECISION_NUMBER_TAKEN', 422);
    }

    public static function invalidStatusTransition(): self
    {
        return new self('انتقال حالة القرار غير مسموح.', 'DECISION_INVALID_STATUS_TRANSITION', 422);
    }

    public static function recommendationInvalid(): self
    {
        return new self('التوصية المصدر غير صالحة للتحويل إلى قرار.', 'DECISION_RECOMMENDATION_INVALID', 422);
    }

    public static function alreadyCreatedFromRecommendation(): self
    {
        return new self('تم إنشاء قرار من هذه التوصية مسبقاً.', 'DECISION_ALREADY_CREATED_FROM_RECOMMENDATION', 422);
    }

    public static function employeeInvalid(): self
    {
        return new self('الموظف المرتبط غير صالح أو غير نشط.', 'DECISION_EMPLOYEE_INVALID', 422);
    }

    public static function organizationInvalid(): self
    {
        return new self('الوحدة التنظيمية غير صالحة أو غير نشطة.', 'DECISION_ORGANIZATION_INVALID', 422);
    }

    public static function invalidDateRange(): self
    {
        return new self('تاريخ الاستحقاق يجب أن يكون بعد أو يساوي تاريخ السريان.', 'DECISION_INVALID_DATE_RANGE', 422);
    }

    public static function immutable(): self
    {
        return new self('لا يمكن تعديل هذا القرار في حالته الحالية.', 'DECISION_IMMUTABLE', 422);
    }

    public static function commentRequired(): self
    {
        return new self('التعليق مطلوب لهذا الإجراء.', 'DECISION_COMMENT_REQUIRED', 422);
    }

    public static function deleteForbidden(): self
    {
        return new self('لا يمكن حذف هذا القرار.', 'DECISION_IMMUTABLE', 422);
    }

    public static function closeNotAllowed(): self
    {
        return new self(
            'لا يمكن إغلاق القرار لوجود مهام مرتبطة غير مكتملة أو غير ملغاة.',
            'DECISION_CLOSE_NOT_ALLOWED',
            422,
        );
    }
}
