<?php

namespace App\Modules\Assets\Exceptions;

use App\Core\Shared\ApiResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class AssetDomainException extends RuntimeException
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

    public static function notAvailable(): self
    {
        return new self('الأصل غير متاح لتسليم عهدة.', 'ASSET_NOT_AVAILABLE', 422);
    }

    public static function alreadyAssigned(): self
    {
        return new self('الأصل لديه عهدة نشطة بالفعل.', 'ASSET_ALREADY_ASSIGNED', 422);
    }

    public static function notAssigned(): self
    {
        return new self('لا توجد عهدة نشطة لاستلامها.', 'ASSET_NOT_ASSIGNED', 422);
    }

    public static function inUse(): self
    {
        return new self('لا يمكن حذف الأصل لوجود عهد أو سجل حالة أو مستندات مرتبطة.', 'ASSET_IN_USE', 422);
    }

    public static function invalidStatusTransition(): self
    {
        return new self('انتقال حالة الأصل غير مسموح.', 'ASSET_INVALID_STATUS_TRANSITION', 422);
    }

    public static function serialExists(): self
    {
        return new self('الرقم التسلسلي مستخدم لأصل آخر ضمن المنشأة.', 'ASSET_SERIAL_ALREADY_EXISTS', 422);
    }

    public static function barcodeExists(): self
    {
        return new self('الباركود مستخدم لأصل آخر ضمن المنشأة.', 'ASSET_BARCODE_ALREADY_EXISTS', 422);
    }

    public static function employeeInvalid(): self
    {
        return new self('الموظف غير صالح أو غير نشط ضمن المنشأة.', 'ASSET_EMPLOYEE_INVALID', 422);
    }

    public static function warehouseInvalid(): self
    {
        return new self('المستودع غير صالح أو غير نشط ضمن المنشأة.', 'ASSET_WAREHOUSE_INVALID', 422);
    }

    public static function organizationInvalid(): self
    {
        return new self('الوحدة التنظيمية غير صالحة أو غير نشطة ضمن المنشأة.', 'ASSET_ORGANIZATION_INVALID', 422);
    }

    public static function categoryInvalid(): self
    {
        return new self('تصنيف الأصل غير صالح أو غير نشط.', 'ASSET_CATEGORY_INVALID', 422);
    }

    public static function categoryInUse(): self
    {
        return new self('لا يمكن حذف التصنيف لارتباطه بأصول.', 'ASSET_CATEGORY_IN_USE', 422);
    }

    public static function categoryNameTaken(): self
    {
        return new self('اسم تصنيف الأصل مستخدم مسبقاً.', 'ASSET_CATEGORY_NAME_TAKEN', 422);
    }

    public static function custodyConflict(): self
    {
        return new self('تعارض في تسليم العهدة؛ حاول مرة أخرى.', 'ASSET_CUSTODY_CONFLICT', 422);
    }

    public static function retireReasonRequired(): self
    {
        return new self('سبب الاستبعاد أو الفقد مطلوب.', 'ASSET_RETIRE_REASON_REQUIRED', 422);
    }

    public static function immutable(): self
    {
        return new self('سجل العهدة أو الحقول النظامية غير قابلة للتعديل.', 'ASSET_IMMUTABLE', 422);
    }

    public static function numberTaken(): self
    {
        return new self('تعذر تخصيص رقم تسلسلي للأصل أو العهدة.', 'ASSET_NUMBER_TAKEN', 422);
    }
}
