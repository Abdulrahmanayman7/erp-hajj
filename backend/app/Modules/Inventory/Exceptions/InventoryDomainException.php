<?php

namespace App\Modules\Inventory\Exceptions;

use App\Core\Shared\ApiResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class InventoryDomainException extends RuntimeException
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

    public static function warehouseInactive(): self
    {
        return new self('المستودع غير نشط ولا يمكن تنفيذ حركة مخزون عليه.', 'WAREHOUSE_INACTIVE', 422);
    }

    public static function warehouseInUse(): self
    {
        return new self('لا يمكن حذف المستودع لوجود حركات أو أرصدة أو مستندات مرتبطة.', 'WAREHOUSE_IN_USE', 422);
    }

    public static function warehouseInvalid(): self
    {
        return new self('المستودع غير صالح أو غير موجود ضمن المنشأة.', 'WAREHOUSE_INACTIVE', 422);
    }

    public static function itemInactive(): self
    {
        return new self('صنف المخزون غير نشط ولا يمكن تنفيذ حركة عليه.', 'INVENTORY_ITEM_INACTIVE', 422);
    }

    public static function itemInUse(): self
    {
        return new self('لا يمكن حذف الصنف لوجود حركات أو أرصدة أو مستندات مرتبطة.', 'INVENTORY_ITEM_IN_USE', 422);
    }

    public static function itemInvalid(): self
    {
        return new self('صنف المخزون غير صالح أو غير موجود ضمن المنشأة.', 'INVENTORY_ITEM_INACTIVE', 422);
    }

    public static function categoryInvalid(): self
    {
        return new self('تصنيف المخزون غير صالح أو غير نشط.', 'INVENTORY_CATEGORY_INVALID', 422);
    }

    public static function categoryInUse(): self
    {
        return new self('لا يمكن حذف التصنيف لارتباطه بأصناف مخزون.', 'INVENTORY_CATEGORY_IN_USE', 422);
    }

    public static function invalidQuantity(): self
    {
        return new self('كمية المخزون غير صالحة.', 'INVENTORY_INVALID_QUANTITY', 422);
    }

    public static function insufficientStock(): self
    {
        return new self('الرصيد المتاح غير كافٍ لإتمام العملية.', 'INVENTORY_INSUFFICIENT_STOCK', 422);
    }

    public static function transferSameWarehouse(): self
    {
        return new self('لا يمكن التحويل إلى نفس المستودع.', 'INVENTORY_TRANSFER_SAME_WAREHOUSE', 422);
    }

    public static function adjustmentReasonRequired(): self
    {
        return new self('سبب التسوية مطلوب.', 'INVENTORY_ADJUSTMENT_REASON_REQUIRED', 422);
    }

    public static function reasonRequired(): self
    {
        return new self('سبب حركة المخزون مطلوب.', 'INVENTORY_REASON_REQUIRED', 422);
    }

    public static function immutable(): self
    {
        return new self('سجلات حركات وأرصدة المخزون غير قابلة للتعديل المباشر.', 'INVENTORY_IMMUTABLE', 422);
    }

    public static function employeeInvalid(): self
    {
        return new self('الموظف المسؤول غير صالح أو غير نشط.', 'INVENTORY_EMPLOYEE_INVALID', 422);
    }

    public static function organizationInvalid(): self
    {
        return new self('الوحدة التنظيمية غير صالحة أو غير نشطة.', 'INVENTORY_ORGANIZATION_INVALID', 422);
    }

    public static function numberTaken(): self
    {
        return new self('تعذر تخصيص رقم تسلسلي للمخزون.', 'INVENTORY_NUMBER_TAKEN', 422);
    }

    public static function invalidUnit(): self
    {
        return new self('وحدة القياس غير مسموحة.', 'INVENTORY_INVALID_QUANTITY', 422);
    }
}
