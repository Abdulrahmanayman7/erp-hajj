<?php

namespace App\Modules\SeasonProjects\Exceptions;

use App\Core\Shared\ApiResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

final class SeasonProjectDomainException extends RuntimeException
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

    public static function softDeleteNotAllowed(string $code = 'SEASON_PROJECT_DELETE_NOT_ALLOWED'): self
    {
        return new self('لا يمكن حذف هذا المشروع في حالته الحالية.', $code);
    }

    public static function softDeleteHasChildren(): self
    {
        return new self(
            'لا يمكن حذف المشروع لوجود مهام أو روابط أوامر شراء مرتبطة.',
            'SEASON_PROJECT_DELETE_HAS_CHILDREN',
        );
    }

    public static function numberConflict(): self
    {
        return new self('تعذر تخصيص رقم المشروع.', 'SEASON_PROJECT_NUMBER_CONFLICT');
    }

    public static function invalidTransition(): self
    {
        return new self('انتقال حالة المشروع غير مسموح.', 'SEASON_PROJECT_INVALID_TRANSITION');
    }

    public static function ownerRequired(): self
    {
        return new self('مالك المشروع (موظف نشط) مطلوب للتفعيل.', 'SEASON_PROJECT_OWNER_REQUIRED');
    }

    public static function ownerInvalid(): self
    {
        return new self('مالك المشروع غير صالح أو غير نشط أو خارج المنشأة.', 'SEASON_PROJECT_OWNER_INVALID');
    }

    public static function updateNotAllowed(): self
    {
        return new self('لا يمكن تعديل المشروع في حالته الحالية.', 'SEASON_PROJECT_UPDATE_NOT_ALLOWED');
    }

    public static function completeRequiresPart3(): self
    {
        return new self(
            'إكمال المشروع معلّق حتى تطبيق قيود المهام بعد الإكمال (Part 3 / E-Q10).',
            'SEASON_PROJECT_COMPLETE_REQUIRES_PART3',
        );
    }

    public static function forceDeleteForbidden(): self
    {
        return new self('الحذف النهائي للمشاريع غير مسموح.', 'SEASON_PROJECT_FORCE_DELETE_FORBIDDEN');
    }

    public static function cancelHasOpenTasks(): self
    {
        return new self(
            'لا يمكن إلغاء المشروع لوجود مهام مفتوحة. أكمل أو ألغِ المهام أولاً.',
            'SEASON_PROJECT_CANCEL_HAS_OPEN_TASKS',
            409,
        );
    }

    public static function taskFrozen(): self
    {
        return new self(
            'المهمة مجمّدة بعد إكمال المشروع؛ يُسمح فقط بالإكمال أو الإلغاء.',
            'SEASON_PROJECT_TASK_FROZEN',
        );
    }

    public static function taskProjectInvalid(): self
    {
        return new self('المشروع المرتبط بالمهمة غير صالح.', 'SEASON_PROJECT_TASK_PROJECT_INVALID');
    }

    public static function taskPhaseInvalid(): self
    {
        return new self('مرحلة المشروع غير صالحة لهذه المهمة.', 'SEASON_PROJECT_TASK_PHASE_INVALID');
    }

    public static function taskCreateNotAllowed(): self
    {
        return new self(
            'لا يمكن إنشاء مهمة على مشروع مكتمل أو ملغى.',
            'SEASON_PROJECT_TASK_CREATE_NOT_ALLOWED',
        );
    }

    public static function phaseSortConflict(): self
    {
        return new self('ترتيب المرحلة مستخدم مسبقاً في هذا المشروع.', 'SEASON_PROJECT_PHASE_SORT_CONFLICT', 409);
    }

    public static function poNotLinkable(): self
    {
        return new self('أمر الشراء غير قابل للربط بحالة ميزانية المشروع.', 'SEASON_PROJECT_PO_NOT_LINKABLE');
    }

    public static function poAlreadyLinked(): self
    {
        return new self('أمر الشراء مرتبط مسبقاً بمشروع آخر.', 'SEASON_PROJECT_PO_ALREADY_LINKED', 409);
    }

    public static function poCurrencyMismatch(): self
    {
        return new self(
            'عملة أمر الشراء تختلف عن عملة ميزانية المشروع؛ التحويل غير مدعوم.',
            'SEASON_PROJECT_PO_CURRENCY_MISMATCH',
        );
    }

    public static function budgetUpdateNotAllowed(): self
    {
        return new self('لا يمكن تعديل الميزانية في حالة المشروع الحالية.', 'SEASON_PROJECT_BUDGET_UPDATE_NOT_ALLOWED');
    }

    public static function budgetCurrencyLocked(): self
    {
        return new self(
            'لا يمكن تغيير عملة الميزانية لوجود أوامر شراء مرتبطة.',
            'SEASON_PROJECT_BUDGET_CURRENCY_LOCKED',
        );
    }

    public static function poUnlinkNotAllowed(): self
    {
        return new self(
            'فك ارتباط أمر الشراء غير مسموح في الحالة الحالية.',
            'SEASON_PROJECT_PO_UNLINK_NOT_ALLOWED',
        );
    }

    /**
     * Part 6 fail-closed until Owner defines unlink-after-posted-GRN budget-release policy.
     */
    public static function poUnlinkHasPostedReceipts(): self
    {
        return new self(
            'لا يمكن فك ارتباط أمر شراء له سندات استلام مرحّلة حتى تُعتمد سياسة تحرير الميزانية.',
            'SEASON_PROJECT_PO_UNLINK_HAS_POSTED_RECEIPTS',
        );
    }
}
