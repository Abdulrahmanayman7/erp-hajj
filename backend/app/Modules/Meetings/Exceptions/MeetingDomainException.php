<?php

namespace App\Modules\Meetings\Exceptions;

use App\Core\Shared\ApiResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class MeetingDomainException extends RuntimeException
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
        return new self('رقم الاجتماع مستخدم مسبقاً.', 'MEETING_NUMBER_TAKEN', 422);
    }

    public static function invalidStatusTransition(): self
    {
        return new self('انتقال حالة الاجتماع غير مسموح.', 'MEETING_INVALID_STATUS_TRANSITION', 422);
    }

    public static function invalidSchedule(): self
    {
        return new self('موعد الاجتماع غير صالح.', 'MEETING_INVALID_SCHEDULE', 422);
    }

    public static function commentRequired(): self
    {
        return new self('التعليق مطلوب لهذا الإجراء.', 'MEETING_COMMENT_REQUIRED', 422);
    }

    public static function notEditable(): self
    {
        return new self('لا يمكن تعديل هذا الاجتماع في حالته الحالية.', 'MEETING_NOT_EDITABLE', 422);
    }

    public static function deleteForbidden(): self
    {
        return new self('لا يمكن حذف هذا الاجتماع.', 'MEETING_DELETE_FORBIDDEN', 422);
    }

    public static function employeeInvalid(): self
    {
        return new self('الموظف المرتبط غير صالح أو غير نشط.', 'MEETING_EMPLOYEE_INVALID', 422);
    }

    public static function organizationInvalid(): self
    {
        return new self('الوحدة التنظيمية غير صالحة أو غير نشطة.', 'MEETING_ORGANIZATION_INVALID', 422);
    }

    public static function attendeeDuplicate(): self
    {
        return new self('الموظف مضاف مسبقاً كحضور في هذا الاجتماع.', 'MEETING_ATTENDEE_DUPLICATE', 422);
    }

    public static function attendeeInvalid(): self
    {
        return new self('سجل الحضور غير صالح.', 'MEETING_ATTENDEE_INVALID', 422);
    }

    public static function minutesRequired(): self
    {
        return new self('محضر الاجتماع مطلوب قبل الإكمال.', 'MEETING_MINUTES_REQUIRED', 422);
    }

    public static function completionRequirementsNotMet(): self
    {
        return new self('متطلبات إكمال الاجتماع غير مستوفاة.', 'MEETING_COMPLETION_REQUIREMENTS_NOT_MET', 422);
    }

    public static function recommendationNotFound(): self
    {
        return new self('التوصية غير موجودة.', 'MEETING_RECOMMENDATION_NOT_FOUND', 404);
    }

    public static function recommendationInUse(): self
    {
        return new self('لا يمكن حذف التوصية لارتباطها بقرار.', 'MEETING_RECOMMENDATION_IN_USE', 422);
    }

    public static function agendaItemInvalid(): self
    {
        return new self('بند جدول الأعمال غير صالح لهذا الاجتماع.', 'MEETING_AGENDA_ITEM_INVALID', 422);
    }
}
