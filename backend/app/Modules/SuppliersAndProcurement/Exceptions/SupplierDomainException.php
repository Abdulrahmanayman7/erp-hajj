<?php

namespace App\Modules\SuppliersAndProcurement\Exceptions;

use App\Core\Shared\ApiResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class SupplierDomainException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly string $errorCode,
        private readonly int $httpStatus = 422,
        /** @var list<array<string, mixed>> */
        private readonly array $reasons = [],
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
     * @return list<array<string, mixed>>
     */
    public function reasons(): array
    {
        return $this->reasons;
    }

    public function render(): JsonResponse
    {
        return ApiResponse::error(
            message: $this->getMessage(),
            code: $this->errorCode(),
            status: $this->status(),
            reasons: $this->reasons,
        );
    }

    public static function numberTaken(): self
    {
        return new self('رقم المورد مستخدم مسبقاً.', 'SUPPLIER_NUMBER_TAKEN', 422);
    }

    public static function invalidTransition(): self
    {
        return new self('انتقال حالة المورد غير مسموح.', 'SUPPLIER_INVALID_TRANSITION', 422);
    }

    public static function notEligible(): self
    {
        return new self('المورد غير مؤهل للتفعيل (متطلبات المرحلة التالية غير مستوفاة).', 'SUPPLIER_NOT_ELIGIBLE', 422);
    }

    /**
     * @param  list<array<string, mixed>>  $reasons
     */
    public static function notEligibleWithReasons(array $reasons): self
    {
        return new self(
            'المورد غير مؤهل للتفعيل: متطلبات التأهيل غير مستوفاة.',
            'SUPPLIER_NOT_ELIGIBLE',
            422,
            $reasons,
        );
    }

    public static function eligibilityConfigMissing(): self
    {
        return new self('لا توجد إعدادات تأهيل معتمدة قابلة للتطبيق على هذا المورد.', 'SUPPLIER_ELIGIBILITY_CONFIG_MISSING', 422);
    }

    public static function eligibilityConfigDraft(): self
    {
        return new self('إعدادات التأهيل في حالة مسودة فقط ولا يوجد إصدار معتمد.', 'SUPPLIER_ELIGIBILITY_CONFIG_DRAFT', 422);
    }

    public static function eligibilityConfigImmutable(): self
    {
        return new self('إصدار إعدادات التأهيل المعتمد غير قابل للتعديل؛ أنشئ مسودة جديدة.', 'SUPPLIER_ELIGIBILITY_CONFIG_IMMUTABLE', 422);
    }

    public static function eligibilityConfigNotDraft(): self
    {
        return new self('إصدار إعدادات التأهيل ليس مسودة.', 'SUPPLIER_ELIGIBILITY_CONFIG_NOT_DRAFT', 422);
    }

    public static function eligibilityConfigNotApproved(): self
    {
        return new self('إصدار إعدادات التأهيل غير معتمد.', 'SUPPLIER_ELIGIBILITY_CONFIG_NOT_APPROVED', 422);
    }

    public static function eligibilityConfigDraftExists(): self
    {
        return new self('توجد مسودة قائمة لمفتاح الإعداد نفسه.', 'SUPPLIER_ELIGIBILITY_CONFIG_DRAFT_EXISTS', 422);
    }

    public static function eligibilityDefsEmpty(): self
    {
        return new self('لا يمكن اعتماد إعدادات تأهيل بدون متطلبات.', 'SUPPLIER_ELIGIBILITY_DEFS_EMPTY', 422);
    }

    public static function eligibilityDefsNoMandatory(): self
    {
        return new self('لا يمكن اعتماد إعدادات تأهيل لا تحتوي على متطلب إلزامي واحد على الأقل.', 'SUPPLIER_ELIGIBILITY_DEFS_NO_MANDATORY', 422);
    }

    public static function documentEvidenceInvalid(): self
    {
        return new self('مستند الإثبات المرتبط بسجل المتطلب غير صالح (مؤرشف أو غير مرتبط بالمورد).', 'SUPPLIER_DOCUMENT_EVIDENCE_INVALID', 422);
    }

    public static function documentMissing(): self
    {
        return new self('مستند إلزامي غير متوفر.', 'SUPPLIER_DOCUMENT_MISSING', 422);
    }

    public static function documentExpired(): self
    {
        return new self('انتهت صلاحية المستند.', 'SUPPLIER_DOCUMENT_EXPIRED', 422);
    }

    public static function documentRejected(): self
    {
        return new self('تم رفض المستند.', 'SUPPLIER_DOCUMENT_REJECTED', 422);
    }

    public static function documentPendingVerification(): self
    {
        return new self('المستند بانتظار التحقق.', 'SUPPLIER_DOCUMENT_PENDING_VERIFICATION', 422);
    }

    public static function documentRecordExists(): self
    {
        return new self('يوجد سجل مستند حالي لهذا المتطلب؛ استخدم الاستبدال.', 'SUPPLIER_DOCUMENT_RECORD_EXISTS', 422);
    }

    public static function documentInvalidState(): self
    {
        return new self('حالة سجل المستند لا تسمح بهذا الإجراء.', 'SUPPLIER_DOCUMENT_INVALID_STATE', 422);
    }

    public static function sodViolation(): self
    {
        return new self('لا يمكن تنفيذ هذا الإجراء من الشخص نفسه (فصل المهام).', 'SUPPLIER_SOD_VIOLATION', 403);
    }

    public static function legalIdDuplicate(): self
    {
        return new self('الهوية القانونية مسجلة لمورد آخر.', 'SUPPLIER_LEGAL_ID_DUPLICATE', 422);
    }

    public static function categoryInUse(): self
    {
        return new self('لا يمكن حذف تصنيف مرتبط بموردين.', 'SUPPLIER_CATEGORY_IN_USE', 422);
    }

    public static function categoryNameTaken(): self
    {
        return new self('اسم التصنيف مستخدم مسبقاً.', 'SUPPLIER_CATEGORY_NAME_TAKEN', 422);
    }

    public static function categoryCodeTaken(): self
    {
        return new self('رمز التصنيف مستخدم مسبقاً.', 'SUPPLIER_CATEGORY_CODE_TAKEN', 422);
    }

    public static function categoryCodeImmutable(): self
    {
        return new self('رمز التصنيف غير قابل للتعديل بعد الإنشاء.', 'SUPPLIER_CATEGORY_CODE_IMMUTABLE', 422);
    }

    public static function categoryInvalid(): self
    {
        return new self('تصنيف المورد غير صالح أو غير نشط.', 'SUPPLIER_CATEGORY_INVALID', 422);
    }

    public static function notEditable(): self
    {
        return new self('لا يمكن تعديل المورد إلا وهو في حالة مسودة.', 'SUPPLIER_NOT_EDITABLE', 422);
    }

    public static function deleteForbidden(): self
    {
        return new self('لا يمكن حذف هذا المورد.', 'SUPPLIER_DELETE_FORBIDDEN', 422);
    }

    public static function coverageCategoryNotLinked(): self
    {
        return new self('التغطية تتطلب ربط التصنيف بالمورد أولاً.', 'SUPPLIER_COVERAGE_CATEGORY_NOT_LINKED', 422);
    }

    public static function geoPlaceInvalid(): self
    {
        return new self('المكان الجغرافي غير صالح.', 'SUPPLIER_GEO_PLACE_INVALID', 422);
    }

    public static function primaryContactRequired(): self
    {
        return new self('يجب تعيين جهة اتصال رئيسية واحدة على الأقل.', 'SUPPLIER_PRIMARY_CONTACT_REQUIRED', 422);
    }
}
