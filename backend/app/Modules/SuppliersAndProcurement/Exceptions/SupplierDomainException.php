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
