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

    public static function bankIbanDuplicate(): self
    {
        return new self(
            'رقم الآيبان مستخدم بالفعل لحساب بنكي آخر داخل المنشأة.',
            'SUPPLIER_BANK_IBAN_DUPLICATE',
            422,
        );
    }

    public static function bankNotFound(): self
    {
        return new self('الحساب البنكي غير موجود.', 'SUPPLIER_BANK_NOT_FOUND', 404);
    }

    public static function bankInvalidStatus(): self
    {
        return new self('حالة الحساب البنكي لا تسمح بهذه العملية.', 'SUPPLIER_BANK_INVALID_STATUS', 422);
    }

    public static function bankSodViolation(): self
    {
        return new self(
            'لا يمكن لمن أنشأ أو عدّل الحساب البنكي التحقق منه أو رفضه.',
            'SUPPLIER_BANK_SOD_VIOLATION',
            403,
        );
    }

    public static function bankIdentifierRequired(): self
    {
        return new self(
            'يجب توفير رقم آيبان أو رقم حساب محلي واحد على الأقل.',
            'SUPPLIER_BANK_IDENTIFIER_REQUIRED',
            422,
        );
    }

    public static function bankCurrencyUnsupported(): self
    {
        return new self('العملة المدعومة للحسابات البنكية هي الريال السعودي فقط.', 'SUPPLIER_BANK_CURRENCY_UNSUPPORTED', 422);
    }

    public static function bankPrimaryNotVerified(): self
    {
        return new self('يمكن تعيين الحساب الأساسي فقط لحساب بنكي متحقَّق.', 'SUPPLIER_BANK_PRIMARY_NOT_VERIFIED', 422);
    }

    public static function bankImmutable(): self
    {
        return new self('لا يمكن تعديل المعرفات البنكية بعد التحقق.', 'SUPPLIER_BANK_IMMUTABLE', 422);
    }

    public static function bankEvidenceInvalid(): self
    {
        return new self(
            'مستند الإثبات البنكي غير صالح لهذا المورد.',
            'SUPPLIER_BANK_EVIDENCE_INVALID',
            422,
        );
    }

    public static function bankHolderNameRequired(): self
    {
        return new self('اسم صاحب الحساب البنكي مطلوب.', 'SUPPLIER_BANK_HOLDER_NAME_REQUIRED', 422);
    }

    public static function bankNameRequired(): self
    {
        return new self('اسم البنك مطلوب.', 'SUPPLIER_BANK_NAME_REQUIRED', 422);
    }

    public static function bankRevealReasonRequired(): self
    {
        return new self(
            'سبب إظهار البيانات البنكية مطلوب ويجب ألا يقل عن 10 أحرف.',
            'SUPPLIER_BANK_REVEAL_REASON_REQUIRED',
            422,
        );
    }

    public static function criteriaPackSodViolation(): self
    {
        return new self(
            'لا يمكن اعتماد حزمة معايير التقييم من المُعِد أو أحد المحررين (فصل المهام).',
            'CRITERIA_PACK_SOD_VIOLATION',
            403,
        );
    }

    public static function criteriaPackNotDraft(): self
    {
        return new self('حزمة معايير التقييم ليست مسودة.', 'CRITERIA_PACK_NOT_DRAFT', 422);
    }

    public static function criteriaPackInvalidWeights(string $sum): self
    {
        return new self(
            "مجموع أوزان المعايير النشطة يجب أن يساوي 100 (±0.01). المجموع الحالي: {$sum}.",
            'CRITERIA_PACK_INVALID_WEIGHTS',
            422,
        );
    }

    public static function criteriaPackEmpty(): self
    {
        return new self('لا يمكن نشر حزمة معايير بدون بنود نشطة.', 'CRITERIA_PACK_EMPTY', 422);
    }

    public static function criteriaPackDraftExists(): self
    {
        return new self('توجد مسودة قائمة لنفس تصنيف المورد.', 'CRITERIA_PACK_DRAFT_EXISTS', 409);
    }

    public static function criteriaPackInvalidStatus(): self
    {
        return new self('حالة حزمة المعايير لا تسمح بهذا الإجراء.', 'CRITERIA_PACK_INVALID_STATUS', 409);
    }

    public static function criteriaPackCategoryInvalid(): self
    {
        return new self('تصنيف المورد غير صالح أو خارج نطاق المنشأة.', 'CRITERIA_PACK_CATEGORY_INVALID', 422);
    }

    public static function evaluationCriteriaMissing(): self
    {
        return new self(
            'لا توجد حزمة معايير تقييم منشورة صالحة لهذا التصنيف.',
            'EVALUATION_CRITERIA_MISSING',
            422,
        );
    }

    public static function evaluationNumberConflict(): self
    {
        return new self('تعذر تخصيص رقم التقييم.', 'EVALUATION_NUMBER_CONFLICT', 409);
    }

    public static function evaluationSodViolation(): self
    {
        return new self(
            'لا يمكن اعتماد أو رفض التقييم من المُعِد نفسه (فصل المهام).',
            'EVALUATION_SOD_VIOLATION',
            403,
        );
    }

    public static function evaluationNotDraft(): self
    {
        return new self('التقييم ليس مسودة.', 'EVALUATION_NOT_DRAFT', 422);
    }

    public static function evaluationInvalidStatus(): self
    {
        return new self('حالة التقييم لا تسمح بهذا الإجراء.', 'EVALUATION_INVALID_STATUS', 409);
    }

    public static function evaluationWeightsInvalid(string $sum): self
    {
        return new self(
            "مجموع أوزان لقطات المعايير يجب أن يساوي 100 (±0.01). المجموع الحالي: {$sum}.",
            'EVALUATION_WEIGHTS_INVALID',
            422,
        );
    }

    public static function evaluationScoreOutOfRange(): self
    {
        return new self('درجة المعيار يجب أن تكون بين 0 و 100.', 'EVALUATION_SCORE_OUT_OF_RANGE', 422);
    }

    public static function evaluationSupplierArchived(): self
    {
        return new self('لا يمكن تقييم مورد مؤرشف.', 'EVALUATION_SUPPLIER_ARCHIVED', 422);
    }

    public static function evaluationCategoryNotLinked(): self
    {
        return new self(
            'تصنيف التقييم غير مرتبط بهذا المورد.',
            'EVALUATION_CATEGORY_NOT_LINKED',
            422,
        );
    }

    public static function evaluationSeasonRequired(): self
    {
        return new self(
            'موسم الحج مطلوب لتقييم ما بعد الموسم.',
            'EVALUATION_SEASON_REQUIRED',
            422,
        );
    }

    public static function evaluationSeasonInvalid(): self
    {
        return new self('الموسم غير صالح أو خارج نطاق المنشأة.', 'EVALUATION_SEASON_INVALID', 404);
    }

    public static function evaluationScoresIncomplete(): self
    {
        return new self(
            'يجب إدخال درجة لكل معيار نشط في الحزمة المنشورة قبل الإرسال.',
            'EVALUATION_SCORES_INCOMPLETE',
            422,
        );
    }

    public static function evaluationAppealNotOpen(): self
    {
        return new self('الاعتراض ليس مفتوحاً.', 'EVALUATION_APPEAL_NOT_OPEN', 409);
    }

    public static function evaluationAppealNotApprovedTarget(): self
    {
        return new self(
            'يمكن تقديم الاعتراض فقط على تقييم معتمد.',
            'EVALUATION_APPEAL_TARGET_INVALID',
            422,
        );
    }

    public static function evaluationAppealExists(): self
    {
        return new self('يوجد اعتراض مفتوح على هذا التقييم.', 'EVALUATION_APPEAL_EXISTS', 409);
    }

    public static function evaluationAppealSodViolation(): self
    {
        return new self(
            'لا يمكن مراجعة الاعتراض من مقدمه أو من مُعِد التقييم (فصل المهام).',
            'EVALUATION_APPEAL_SOD_VIOLATION',
            403,
        );
    }

    public static function evaluationImmutable(): self
    {
        return new self('التقييم المعتمد أو المرفوض غير قابل للتعديل.', 'EVALUATION_IMMUTABLE', 422);
    }

    public static function prNumberConflict(): self
    {
        return new self('تعذر تخصيص رقم طلب الشراء.', 'PR_NUMBER_CONFLICT', 409);
    }

    public static function prInvalidTransition(): self
    {
        return new self('حالة طلب الشراء لا تسمح بهذا الإجراء.', 'PR_INVALID_TRANSITION', 409);
    }

    public static function prEditLocked(): self
    {
        return new self('طلب الشراء غير قابل للتعديل في حالته الحالية.', 'PR_EDIT_LOCKED', 422);
    }

    public static function prSeasonRequired(): self
    {
        return new self('موسم الحج مطلوب لطلب شراء موسمي.', 'PR_SEASON_REQUIRED', 422);
    }

    public static function prSeasonInvalid(): self
    {
        return new self('الموسم غير صالح أو خارج نطاق المنشأة.', 'PR_SEASON_INVALID', 404);
    }

    public static function prOrgUnitInvalid(): self
    {
        return new self('الوحدة التنظيمية غير صالحة أو غير نشطة.', 'PR_ORG_UNIT_INVALID', 422);
    }

    public static function prOrgUnitNotFound(): self
    {
        return new self('الوحدة التنظيمية غير موجودة.', 'PR_ORG_UNIT_NOT_FOUND', 404);
    }

    public static function prSupplierNotFound(): self
    {
        return new self('المورد غير موجود.', 'PR_SUPPLIER_NOT_FOUND', 404);
    }

    public static function prSupplierArchived(): self
    {
        return new self('لا يمكن ربط مورد مؤرشف بطلب الشراء.', 'PR_SUPPLIER_ARCHIVED', 422);
    }

    public static function prSupplierNotEligible(): self
    {
        return new self('المورد غير مؤهل للاستخدام في طلب الشراء.', 'PR_SUPPLIER_NOT_ELIGIBLE', 422);
    }

    public static function prUnpricedReasonRequired(): self
    {
        return new self('سبب غياب السعر مطلوب للبنود غير المسعّرة.', 'PR_UNPRICED_REASON_REQUIRED', 422);
    }

    public static function prLinesRequired(): self
    {
        return new self('يجب إضافة بند واحد على الأقل قبل الإرسال.', 'PR_LINES_REQUIRED', 422);
    }

    public static function prDuplicateAckRequired(): self
    {
        return new self('يجب الإقرار بتحذير التشابه قبل الإرسال.', 'PR_DUPLICATE_ACK_REQUIRED', 422);
    }

    public static function prCancelReasonRequired(): self
    {
        return new self('سبب الإلغاء مطلوب.', 'PR_CANCEL_REASON_REQUIRED', 422);
    }

    public static function prUrgencyJustificationRequired(): self
    {
        return new self('مبرر الاستعجال مطلوب للأولوية العاجلة أو الطارئة.', 'PR_URGENCY_JUSTIFICATION_REQUIRED', 422);
    }

    public static function prFinancialReviewVersionMismatch(): self
    {
        return new self('رقم إصدار المراجعة المالية غير متوافق مع الإصدار الحالي.', 'PR_FINANCIAL_REVIEW_VERSION_MISMATCH', 422);
    }

    public static function prProcurementEngaged(): self
    {
        return new self('لا يمكن إلغاء الطلب بعد بدء عملية الشراء اللاحقة.', 'PR_PROCUREMENT_ENGAGED', 422);
    }

    public static function prJustificationTooShort(): self
    {
        return new self('المبرر يجب ألا يقل عن 10 أحرف.', 'PR_JUSTIFICATION_TOO_SHORT', 422);
    }

    public static function matrixNotDraft(): self
    {
        return new self('مصفوفة الاعتماد ليست مسودة.', 'MATRIX_NOT_DRAFT', 409);
    }

    public static function matrixLevelsRequired(): self
    {
        return new self('يجب تعريف مستوى اعتماد واحد على الأقل قبل النشر.', 'MATRIX_LEVELS_REQUIRED', 422);
    }

    public static function matrixRulesRequired(): self
    {
        return new self('يجب تعريف قاعدة اعتماد واحدة على الأقل قبل النشر.', 'MATRIX_RULES_REQUIRED', 422);
    }

    public static function matrixLevelsInvalid(): self
    {
        return new self(
            'ترتيب مستويات الاعتماد غير صالح؛ يجب أن يكون متتابعاً وفريداً من 1 إلى N.',
            'MATRIX_LEVELS_INVALID',
            422,
        );
    }

    public static function matrixUnpricedRuleRequired(): self
    {
        return new self(
            'يجب وجود قاعدة واحدة على الأقل تنطبق عند غياب التسعير قبل النشر.',
            'MATRIX_UNPRICED_RULE_REQUIRED',
            422,
        );
    }

    public static function matrixRulesAmbiguous(): self
    {
        return new self(
            'قواعد مصفوفة الاعتماد غامضة (تداخل نطاقات بمساواة الخصوصية).',
            'MATRIX_RULES_AMBIGUOUS',
            422,
        );
    }

    public static function ruleAmountRangeInvalid(): self
    {
        return new self(
            'نطاق المبلغ غير صالح؛ الحد الأدنى يجب ألا يتجاوز الحد الأعلى.',
            'RULE_AMOUNT_RANGE_INVALID',
            422,
        );
    }

    public static function matrixAlreadyPublished(): self
    {
        return new self(
            'مصفوفة الاعتماد منشورة مسبقاً؛ أنشئ مسودة جديدة للتعديل.',
            'MATRIX_ALREADY_PUBLISHED',
            409,
        );
    }

    public static function levelOrderConflict(): self
    {
        return new self(
            'ترتيب المستوى مستخدم مسبقاً في هذه المصفوفة.',
            'LEVEL_ORDER_CONFLICT',
            409,
        );
    }

    public static function authorityUserInvalid(): self
    {
        return new self(
            'المستخدم غير صالح لمنح سلطة الاعتماد أو خارج نطاق المنشأة.',
            'AUTHORITY_USER_INVALID',
            422,
        );
    }

    public static function authorityAmountInvalid(): self
    {
        return new self(
            'سقف المبلغ يجب أن يكون أكبر من صفر.',
            'AUTHORITY_AMOUNT_INVALID',
            422,
        );
    }

    public static function authorityOverlap(): self
    {
        return new self(
            'توجد سلطة اعتماد نشطة متداخلة لنفس المستخدم في هذه الفترة.',
            'AUTHORITY_OVERLAP',
            422,
        );
    }

    public static function authorityAlreadyRevoked(): self
    {
        return new self(
            'سلطة الاعتماد ملغاة مسبقاً.',
            'AUTHORITY_ALREADY_REVOKED',
            409,
        );
    }

    public static function authorityDateRangeInvalid(): self
    {
        return new self(
            'نطاق تاريخ سريان سلطة الاعتماد غير صالح.',
            'AUTHORITY_DATE_RANGE_INVALID',
            422,
        );
    }

    public static function prMatrixMissing(): self
    {
        return new self(
            'لا توجد مصفوفة اعتماد منشورة لهذه المنشأة.',
            'PR_MATRIX_MISSING',
            422,
        );
    }

    public static function prRoutingBlocked(?string $message = null): self
    {
        return new self(
            $message ?? 'تعذر توجيه طلب الشراء لمسار الاعتماد.',
            'PR_ROUTING_BLOCKED',
            422,
        );
    }

    public static function prApprovalRuleAmbiguous(): self
    {
        return new self(
            'قواعد الاعتماد غامضة ولا يمكن اختيار مسار واحد.',
            'PR_APPROVAL_RULE_AMBIGUOUS',
            422,
        );
    }

    public static function prUnpricedPathMissing(): self
    {
        return new self(
            'لا يوجد مسار اعتماد للبنود غير المسعّرة.',
            'PR_UNPRICED_PATH_MISSING',
            422,
        );
    }

    public static function prStepNotPending(): self
    {
        return new self(
            'خطوة الاعتماد ليست معلّقة أو لم تعد الخطوة الحالية.',
            'PR_STEP_NOT_PENDING',
            409,
        );
    }

    public static function prConcurrentDecision(): self
    {
        return new self(
            'تم اتخاذ قرار اعتماد متزامن؛ أعد تحميل الطلب.',
            'PR_CONCURRENT_DECISION',
            409,
        );
    }

    public static function prSodViolation(): self
    {
        return new self(
            'تعارض فصل المهام: المعتمد غير مؤهل لهذه الخطوة.',
            'PR_SOD_VIOLATION',
            422,
        );
    }

    public static function prFinancialReviewRequired(): self
    {
        return new self(
            'المراجعة المالية مطلوبة قبل الاعتماد النهائي.',
            'PR_FINANCIAL_REVIEW_REQUIRED',
            422,
        );
    }

    public static function prFinancialReviewNotCleared(): self
    {
        return new self(
            'المراجعة المالية الحالية لا تسمح بالاعتماد النهائي.',
            'PR_FINANCIAL_REVIEW_NOT_CLEARED',
            422,
        );
    }

    public static function prFinancialReviewerSod(): self
    {
        return new self(
            'مراجع المالية لا يجوز له إتمام الاعتماد النهائي لنفس الإصدار.',
            'PR_FINANCIAL_REVIEWER_SOD',
            422,
        );
    }

    public static function prNoEligibleFinalApprover(): self
    {
        return new self(
            'لا يوجد معتمد نهائي مؤهل بعد تطبيق فصل المهام.',
            'PR_NO_ELIGIBLE_FINAL_APPROVER',
            422,
        );
    }

    public static function prRejectReasonRequired(): self
    {
        return new self('سبب الرفض مطلوب (10 أحرف على الأقل).', 'PR_REJECT_REASON_REQUIRED', 422);
    }

    public static function prReturnReasonRequired(): self
    {
        return new self('سبب الإعادة للتعديل مطلوب (10 أحرف على الأقل).', 'PR_RETURN_REASON_REQUIRED', 422);
    }

    public static function prDelegationInvalid(?string $message = null): self
    {
        return new self(
            $message ?? 'تفويض الاعتماد غير صالح.',
            'PR_DELEGATION_INVALID',
            422,
        );
    }

    public static function slaPolicyInvalid(?string $message = null): self
    {
        return new self(
            $message ?? 'سياسة SLA غير صالحة.',
            'SLA_POLICY_INVALID',
            422,
        );
    }

    public static function dgNumberConflict(): self
    {
        return new self('تعذر تخصيص رقم مجموعة التخطيط.', 'DG_NUMBER_CONFLICT', 409);
    }

    public static function dgPrNotApproved(): self
    {
        return new self(
            'يمكن ربط طلبات الشراء المعتمدة فقط بمجموعة التخطيط.',
            'DG_PR_NOT_APPROVED',
            422,
        );
    }

    public static function dgPrAlreadyGrouped(): self
    {
        return new self(
            'طلب الشراء مرتبط بالفعل بمجموعة تخطيط نشطة.',
            'DG_PR_ALREADY_GROUPED',
            409,
        );
    }

    public static function dgMembershipLocked(): self
    {
        return new self(
            'عضوية مجموعة التخطيط مقفلة في حالتها الحالية.',
            'DG_MEMBERSHIP_LOCKED',
            422,
        );
    }

    public static function dgMembershipLockedEngaged(): self
    {
        return new self(
            'لا يمكن تعديل عضوية المجموعة بعد بدء عملية شراء لاحقة لأحد الطلبات المرتبطة.',
            'DG_MEMBERSHIP_LOCKED_ENGAGED',
            422,
        );
    }

    public static function dgInvalidTransition(): self
    {
        return new self(
            'انتقال حالة مجموعة التخطيط غير مسموح.',
            'DG_INVALID_TRANSITION',
            422,
        );
    }

    public static function dgEngagementActive(): self
    {
        return new self(
            'لا يمكن إلغاء تجميد المجموعة بينما يوجد طلب مرتبط بعملية شراء لاحقة.',
            'DG_ENGAGEMENT_ACTIVE',
            422,
        );
    }

    public static function dgNotEditable(): self
    {
        return new self(
            'لا يمكن تعديل مجموعة التخطيط في حالتها الحالية.',
            'DG_NOT_EDITABLE',
            422,
        );
    }

    public static function dgMembershipNotFound(): self
    {
        return new self(
            'طلب الشراء غير مرتبط بهذه المجموعة.',
            'DG_MEMBERSHIP_NOT_FOUND',
            404,
        );
    }

    public static function dgPrNotFound(): self
    {
        return new self('طلب الشراء غير موجود.', 'DG_PR_NOT_FOUND', 404);
    }

    public static function reportDateRangeInvalid(?string $message = null): self
    {
        return new self(
            $message ?? 'نطاق تاريخ التقرير غير صالح (الحد الأقصى 366 يوماً).',
            'REPORT_DATE_RANGE_INVALID',
            422,
        );
    }

    public static function reportExportTooLarge(): self
    {
        return new self(
            'تصدير التقرير يتجاوز الحد الأقصى (10000 صف). ضيّق نطاق التاريخ أو عوامل التصفية.',
            'REPORT_EXPORT_TOO_LARGE',
            422,
        );
    }
}
