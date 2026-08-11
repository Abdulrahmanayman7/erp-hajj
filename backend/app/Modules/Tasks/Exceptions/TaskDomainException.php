<?php

namespace App\Modules\Tasks\Exceptions;

use App\Core\Shared\ApiResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class TaskDomainException extends RuntimeException
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
        return new self('رقم المهمة مستخدم مسبقاً.', 'TASK_NUMBER_TAKEN', 422);
    }

    public static function invalidStatusTransition(): self
    {
        return new self('انتقال حالة المهمة غير مسموح.', 'TASK_INVALID_STATUS_TRANSITION', 422);
    }

    public static function decisionInvalid(): self
    {
        return new self('القرار المرتبط غير صالح لهذه المهمة.', 'TASK_DECISION_INVALID', 422);
    }

    public static function employeeInvalid(): self
    {
        return new self('الموظف المكلّف غير صالح أو غير نشط.', 'TASK_EMPLOYEE_INVALID', 422);
    }

    public static function organizationInvalid(): self
    {
        return new self('الوحدة التنظيمية غير صالحة أو غير نشطة.', 'TASK_ORGANIZATION_INVALID', 422);
    }

    public static function invalidDateRange(): self
    {
        return new self('تاريخ الاستحقاق يجب أن يكون بعد أو يساوي تاريخ البدء.', 'TASK_INVALID_DATE_RANGE', 422);
    }

    public static function immutable(): self
    {
        return new self('لا يمكن تعديل هذه المهمة في حالتها الحالية.', 'TASK_IMMUTABLE', 422);
    }

    public static function assigneeRequired(): self
    {
        return new self('يجب تعيين موظف مسؤول عن التنفيذ.', 'TASK_ASSIGNEE_REQUIRED', 422);
    }

    public static function completionRequirementsNotMet(): self
    {
        return new self('ملاحظات الإنجاز مطلوبة لإكمال المهمة.', 'TASK_COMPLETION_REQUIREMENTS_NOT_MET', 422);
    }

    public static function commentRequired(): self
    {
        return new self('التعليق مطلوب لهذا الإجراء.', 'TASK_COMMENT_REQUIRED', 422);
    }

    public static function progressInvalid(): self
    {
        return new self('نسبة التقدم يجب أن تكون بين 0 و 100.', 'TASK_INVALID_STATUS_TRANSITION', 422);
    }

    public static function deleteForbidden(): self
    {
        return new self('لا يمكن حذف هذه المهمة.', 'TASK_IMMUTABLE', 422);
    }
}
