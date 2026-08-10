<?php

namespace App\Modules\Documents\Exceptions;

use App\Core\Shared\ApiResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class DocumentDomainException extends RuntimeException
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
        return new self('رقم المستند مستخدم مسبقاً.', 'DOCUMENT_NUMBER_TAKEN', 422);
    }

    public static function invalidFile(): self
    {
        return new self('نوع الملف غير مسموح أو غير متوافق.', 'DOCUMENT_INVALID_FILE', 422);
    }

    public static function fileTooLarge(): self
    {
        return new self('حجم الملف يتجاوز الحد المسموح (20 ميغابايت).', 'DOCUMENT_FILE_TOO_LARGE', 422);
    }

    public static function fileRequired(): self
    {
        return new self('الملف مطلوب.', 'DOCUMENT_INVALID_FILE', 422);
    }

    public static function fileMissing(): self
    {
        return new self('ملف المستند غير موجود على التخزين.', 'DOCUMENT_FILE_MISSING', 404);
    }

    public static function linkInvalid(): self
    {
        return new self('السجل المرتبط غير صالح أو غير موجود ضمن المنشأة.', 'DOCUMENT_LINK_INVALID', 422);
    }

    public static function categoryInvalid(): self
    {
        return new self('تصنيف المستند غير صالح أو غير نشط.', 'DOCUMENT_CATEGORY_INVALID', 422);
    }

    public static function categoryInUse(): self
    {
        return new self('لا يمكن حذف التصنيف لارتباطه بمستندات.', 'DOCUMENT_CATEGORY_IN_USE', 422);
    }

    public static function invalidStatusTransition(): self
    {
        return new self('انتقال حالة المستند غير مسموح.', 'DOCUMENT_INVALID_STATUS_TRANSITION', 422);
    }

    public static function immutable(): self
    {
        return new self('لا يمكن تعديل حقول التخزين لهذا المستند.', 'DOCUMENT_IMMUTABLE', 422);
    }

    public static function storageFailed(): self
    {
        return new self('تعذر حفظ الملف في التخزين الخاص.', 'DOCUMENT_STORAGE_FAILED', 422);
    }

    public static function entityInUse(): self
    {
        return new self('لا يمكن حذف السجل لوجود مستندات مرتبطة به.', 'DOCUMENT_ENTITY_IN_USE', 422);
    }
}
