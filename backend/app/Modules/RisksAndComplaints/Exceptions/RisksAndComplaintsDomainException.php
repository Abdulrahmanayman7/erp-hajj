<?php

namespace App\Modules\RisksAndComplaints\Exceptions;

use App\Core\Shared\ApiResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class RisksAndComplaintsDomainException extends RuntimeException
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

    public static function riskNumberUnavailable(): self
    {
        return new self('تعذر إصدار رقم الخطر. أعد المحاولة.', 'RISK_NUMBER_UNAVAILABLE', 409);
    }

    public static function complaintNumberUnavailable(): self
    {
        return new self('تعذر إصدار رقم الشكوى. أعد المحاولة.', 'COMPLAINT_NUMBER_UNAVAILABLE', 409);
    }

    public static function riskDeleteNotAllowed(): self
    {
        return new self('لا يمكن حذف الخطر إلا وهو في حالة محددة.', 'RISK_DELETE_NOT_ALLOWED', 422);
    }

    public static function complaintDeleteNotAllowed(): self
    {
        return new self('لا يمكن حذف الشكوى إلا وهي جديدة وغير معينة.', 'COMPLAINT_DELETE_NOT_ALLOWED', 422);
    }

    public static function riskMatrixOutOfRange(): self
    {
        return new self('قيم الاحتمال والأثر يجب أن تكون بين 1 و 5.', 'RISK_MATRIX_OUT_OF_RANGE', 422);
    }

    public static function riskInvalidTransition(): self
    {
        return new self('انتقال حالة الخطر غير مسموح.', 'RISK_INVALID_TRANSITION', 422);
    }

    public static function riskSelfApprovalForbidden(): self
    {
        return new self('لا يجوز لمن أنشأ الخطر اعتماد تقييمه.', 'RISK_SELF_APPROVAL_FORBIDDEN', 422);
    }

    public static function riskAssessmentIncomplete(): self
    {
        return new self('يجب اكتمال تقييم الاحتمال والأثر قبل الاعتماد.', 'RISK_ASSESSMENT_INCOMPLETE', 422);
    }

    public static function riskTreatmentRequired(): self
    {
        return new self('خطة المعالجة مطلوبة قبل اعتماد التقييم.', 'RISK_TREATMENT_REQUIRED', 422);
    }

    public static function riskImmutable(): self
    {
        return new self('لا يمكن تعديل هذا الخطر في حالته الحالية.', 'RISK_IMMUTABLE', 422);
    }

    public static function riskEmployeeInvalid(): self
    {
        return new self('الموظف غير صالح أو غير نشط ضمن المنشأة الحالية.', 'RISK_EMPLOYEE_INVALID', 422);
    }

    public static function linkedEntityInvalid(): self
    {
        return new self('الكيان المرتبط غير صالح أو خارج نطاق الموسم/المنشأة.', 'LINKED_ENTITY_INVALID', 422);
    }

    public static function seasonProjectLinkUnavailable(): self
    {
        return new self('ربط المشروع الموسمي غير متاح حالياً.', 'SEASON_PROJECT_LINK_UNAVAILABLE', 422);
    }

    public static function conflictStaleState(): self
    {
        return new self('حالة السجل تغيّرت. أعد التحميل ثم حاول مرة أخرى.', 'CONFLICT_STALE_STATE', 409);
    }

    public static function riskClosureOutcomeRequired(): self
    {
        return new self('نتيجة الإغلاق مطلوبة ومعتمدة.', 'RISK_CLOSURE_OUTCOME_REQUIRED', 422);
    }

    public static function riskNumberTaken(): self
    {
        return new self('رقم الخطر مستخدم مسبقاً.', 'RISK_NUMBER_TAKEN', 422);
    }

    public static function complaintInvalidTransition(): self
    {
        return new self('انتقال حالة الشكوى غير مسموح.', 'COMPLAINT_INVALID_TRANSITION', 422);
    }

    public static function complaintImmutable(): self
    {
        return new self('لا يمكن تعديل هذه الشكوى في حالتها الحالية.', 'COMPLAINT_IMMUTABLE', 422);
    }

    public static function complaintEmployeeInvalid(): self
    {
        return new self('الموظف غير صالح أو غير نشط ضمن المنشأة الحالية.', 'COMPLAINT_EMPLOYEE_INVALID', 422);
    }

    public static function complaintCloseReasonRequired(): self
    {
        return new self('سبب الإغلاق مطلوب.', 'COMPLAINT_CLOSE_REASON_REQUIRED', 422);
    }

    public static function complaintReopenReasonRequired(): self
    {
        return new self('سبب إعادة الفتح مطلوب.', 'COMPLAINT_REOPEN_REASON_REQUIRED', 422);
    }

    public static function complaintEscalateReasonRequired(): self
    {
        return new self('سبب التصعيد مطلوب.', 'COMPLAINT_ESCALATE_REASON_REQUIRED', 422);
    }

    public static function complaintResolutionRequired(): self
    {
        return new self('نص الحل مطلوب.', 'COMPLAINT_RESOLUTION_REQUIRED', 422);
    }

    public static function complaintResolveNotAssignee(): self
    {
        return new self('يجب أن يكون مقدّم الحل هو الموظف المعيَّن على الشكوى.', 'COMPLAINT_RESOLVE_NOT_ASSIGNEE', 422);
    }

    public static function complaintEscalateForbidden(): self
    {
        return new self('غير مصرح بتصعيد هذه الشكوى.', 'COMPLAINT_ESCALATE_FORBIDDEN', 422);
    }

    public static function complaintCategoryInvalid(): self
    {
        return new self('تصنيف الشكوى غير صالح.', 'COMPLAINT_CATEGORY_INVALID', 422);
    }

    public static function complaintNumberTaken(): self
    {
        return new self('رقم الشكوى مستخدم مسبقاً.', 'COMPLAINT_NUMBER_TAKEN', 422);
    }
}
