<?php

namespace App\Modules\SeasonPilgrims\Exceptions;

use App\Core\Shared\ApiResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class SeasonPilgrimDomainException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $errors
     */
    public function __construct(
        string $message,
        private readonly string $errorCode,
        private readonly int $httpStatus = 422,
        private readonly array $errors = [],
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

    /**
     * @return array<string, mixed>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    public function render(): JsonResponse
    {
        return ApiResponse::error(
            message: $this->getMessage(),
            code: $this->errorCode(),
            errors: $this->errors,
            status: $this->status(),
        );
    }

    // ─── Season ──────────────────────────────────────────────────────────────

    public static function seasonCodeTaken(): self
    {
        return new self('رمز الموسم مستخدم مسبقًا.', 'SEASON_CODE_TAKEN');
    }

    public static function seasonHajjYearTaken(): self
    {
        return new self('يوجد موسم آخر لنفس سنة الحج.', 'SEASON_HAJJ_YEAR_TAKEN');
    }

    public static function seasonWindowInvalid(): self
    {
        return new self('تاريخ نهاية النافذة التشغيلية يجب أن يكون بعد تاريخ بدايتها أو مساويًا له.', 'SEASON_OPERATIONAL_WINDOW_INVALID');
    }

    public static function seasonActiveExists(): self
    {
        return new self('يوجد موسم نشط آخر. لا يمكن تفعيل أكثر من موسم في الوقت نفسه.', 'SEASON_ACTIVE_EXISTS');
    }

    public static function seasonInvalidTransition(): self
    {
        return new self('انتقال حالة الموسم غير مسموح.', 'SEASON_INVALID_TRANSITION');
    }

    public static function seasonClosed(): self
    {
        return new self('الموسم مغلق ولا يقبل التعديل.', 'SEASON_CLOSED');
    }

    public static function seasonNotActive(): self
    {
        return new self('يتطلب هذا الإجراء موسمًا نشطًا.', 'SEASON_NOT_ACTIVE');
    }

    public static function seasonDeleteForbidden(): self
    {
        return new self('لا يمكن حذف الموسم: يُسمح بحذف المسودة غير المفعّلة والخالية من البيانات فقط.', 'SEASON_DELETE_FORBIDDEN');
    }

    /**
     * @param  list<array<string, mixed>>  $results
     */
    public static function resourceReferenced(): self
    {
        return new self('لا يمكن الحذف لارتباط السجل ببيانات تشغيلية في وحدة أخرى.', 'SEASON_RESOURCE_REFERENCED');
    }

    public static function seasonClosingBlocked(array $results): self
    {
        return new self('لا يمكن إغلاق الموسم لوجود موانع في فحص الجاهزية.', 'SEASON_CLOSING_BLOCKED', 422, [
            'readiness' => $results,
        ]);
    }

    public static function seasonContextMissing(): self
    {
        return new self('لا يوجد موسم نشط. حدّد الموسم المطلوب.', 'SEASON_CONTEXT_MISSING');
    }

    public static function seasonContextInvalid(): self
    {
        return new self('معرّف الموسم المحدد غير صالح.', 'SEASON_CONTEXT_INVALID');
    }

    // ─── Pilgrim / identity ──────────────────────────────────────────────────

    public static function identityDocumentDuplicate(int $existingPilgrimId): self
    {
        return new self('وثيقة الهوية مسجلة لحاج آخر في المنشأة.', 'PILGRIM_IDENTITY_DOCUMENT_DUPLICATE', 422, [
            'duplicate_pilgrim_id' => [$existingPilgrimId],
        ]);
    }

    public static function identityDocumentInvalid(): self
    {
        return new self('وثيقة الهوية لا تخص هذا الحاج.', 'PILGRIM_IDENTITY_DOCUMENT_INVALID');
    }

    public static function pilgrimInUse(): self
    {
        return new self('لا يمكن حذف الحاج لارتباطه بمشاركات موسمية.', 'PILGRIM_IN_USE');
    }

    // ─── Season pilgrim ──────────────────────────────────────────────────────

    public static function seasonPilgrimAlreadyRegistered(): self
    {
        return new self('الحاج مسجل مسبقًا في هذا الموسم.', 'SEASON_PILGRIM_ALREADY_REGISTERED');
    }

    public static function seasonPilgrimInvalidTransition(): self
    {
        return new self('انتقال حالة مشاركة الحاج غير مسموح.', 'SEASON_PILGRIM_INVALID_TRANSITION');
    }

    public static function seasonPilgrimTerminal(): self
    {
        return new self('مشاركة الحاج منتهية أو ملغاة ولا تقبل هذا التعديل.', 'SEASON_PILGRIM_TERMINAL');
    }

    /**
     * @param  list<string>  $violations
     */
    public static function activationRequirementsNotMet(array $violations): self
    {
        return new self('لم تكتمل متطلبات تفعيل مشاركة الحاج.', 'SEASON_PILGRIM_ACTIVATION_REQUIREMENTS_NOT_MET', 422, [
            'requirements' => $violations,
        ]);
    }

    public static function pilgrimGroupLocked(): self
    {
        return new self('لا يمكن تغيير مجموعة الحاج بعد تفعيل المشاركة.', 'SEASON_PILGRIM_GROUP_LOCKED');
    }

    public static function snapshotLocked(): self
    {
        return new self('لقطة الهوية مقفلة. استخدم إجراء التصحيح التاريخي.', 'SEASON_PILGRIM_SNAPSHOT_LOCKED');
    }

    public static function snapshotNotLocked(): self
    {
        return new self('لقطة الهوية غير مقفلة بعد. استخدم إجراء التحديث.', 'SEASON_PILGRIM_SNAPSHOT_NOT_LOCKED');
    }

    public static function seasonPilgrimDeleteForbidden(): self
    {
        return new self('لا يمكن حذف المشاركة لارتباطها تشغيليًا. استخدم الإلغاء.', 'SEASON_PILGRIM_DELETE_FORBIDDEN');
    }

    public static function referenceNumberUnavailable(): self
    {
        return new self('تعذر إصدار الرقم المرجعي الموسمي. أعد المحاولة.', 'SEASON_PILGRIM_REFERENCE_UNAVAILABLE', 409);
    }

    // ─── Program ─────────────────────────────────────────────────────────────

    public static function programInvalid(): self
    {
        return new self('البرنامج غير موجود في هذا الموسم.', 'SEASON_PROGRAM_INVALID');
    }

    public static function programInactive(): self
    {
        return new self('البرنامج غير نشط ولا يقبل تعيينات جديدة.', 'SEASON_PROGRAM_INACTIVE');
    }

    public static function programRequired(): self
    {
        return new self('لا يمكن إزالة البرنامج بعد تفعيل المشاركة.', 'SEASON_PILGRIM_PROGRAM_REQUIRED');
    }

    public static function programUnchanged(): self
    {
        return new self('البرنامج المحدد هو البرنامج الحالي.', 'SEASON_PILGRIM_PROGRAM_UNCHANGED');
    }

    public static function programInUse(): self
    {
        return new self('لا يمكن حذف برنامج مستخدم. يمكن إيقافه بدلًا من ذلك.', 'SEASON_PROGRAM_IN_USE');
    }

    public static function programCodeTaken(): self
    {
        return new self('رمز البرنامج مستخدم في هذا الموسم.', 'SEASON_PROGRAM_CODE_TAKEN');
    }

    // ─── Pilgrim group ───────────────────────────────────────────────────────

    public static function pilgrimGroupInvalid(): self
    {
        return new self('مجموعة الحجاج غير موجودة في هذا الموسم.', 'SEASON_PILGRIM_GROUP_INVALID');
    }

    public static function pilgrimGroupCodeTaken(): self
    {
        return new self('رمز المجموعة مستخدم في هذا الموسم.', 'SEASON_PILGRIM_GROUP_CODE_TAKEN');
    }

    public static function pilgrimGroupInUse(): self
    {
        return new self('لا يمكن حذف مجموعة لها أعضاء أو سجل مشرفين.', 'SEASON_PILGRIM_GROUP_IN_USE');
    }

    public static function supervisorEmployeeInvalid(): self
    {
        return new self('الموظف غير موجود أو غير نشط.', 'PILGRIM_GROUP_SUPERVISOR_EMPLOYEE_INVALID');
    }

    public static function supervisorDuplicate(): self
    {
        return new self('الموظف مشرف حالي في هذه المجموعة.', 'PILGRIM_GROUP_SUPERVISOR_DUPLICATE');
    }

    public static function primarySupervisorRequired(): self
    {
        return new self('يجب تعيين مشرف رئيسي قبل إضافة مساعدين.', 'PILGRIM_GROUP_PRIMARY_SUPERVISOR_REQUIRED');
    }

    public static function primarySupervisorEndBlocked(): self
    {
        return new self('لا يمكن إنهاء المشرف الرئيسي مع وجود مساعدين. عيّن مشرفًا رئيسيًا بديلًا.', 'PILGRIM_GROUP_PRIMARY_SUPERVISOR_END_BLOCKED');
    }

    public static function supervisorAssignmentEnded(): self
    {
        return new self('تكليف المشرف منتهٍ مسبقًا.', 'PILGRIM_GROUP_SUPERVISOR_ALREADY_ENDED');
    }

    // ─── Family group ────────────────────────────────────────────────────────

    public static function familyGroupInvalid(): self
    {
        return new self('مجموعة العائلة غير موجودة في هذا الموسم.', 'SEASON_FAMILY_GROUP_INVALID');
    }

    public static function familyGroupNotEmpty(): self
    {
        return new self('لا يمكن حذف مجموعة عائلة تضم أعضاء.', 'SEASON_FAMILY_GROUP_NOT_EMPTY');
    }

    public static function familyMemberInvalid(): self
    {
        return new self('الحاج ليس عضوًا في مجموعة العائلة.', 'SEASON_FAMILY_MEMBER_INVALID');
    }
}
