<?php

namespace App\Modules\Employees\Exceptions;

use App\Core\Shared\ApiResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class EmployeeDomainException extends RuntimeException
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
        return new self('الرقم الوظيفي مستخدم مسبقاً.', 'EMPLOYEE_NUMBER_TAKEN', 422);
    }

    public static function supervisorInvalid(): self
    {
        return new self('المشرف المباشر غير صالح أو غير نشط.', 'EMPLOYEE_SUPERVISOR_INVALID', 422);
    }

    public static function supervisorCycle(): self
    {
        return new self('لا يمكن إنشاء سلسلة إشراف دائرية.', 'EMPLOYEE_SUPERVISOR_CYCLE', 422);
    }

    public static function userInvalid(): self
    {
        return new self('حساب المستخدم غير صالح للربط.', 'EMPLOYEE_USER_INVALID', 422);
    }

    public static function userAlreadyLinked(): self
    {
        return new self('حساب المستخدم مرتبط بموظف آخر.', 'EMPLOYEE_USER_ALREADY_LINKED', 422);
    }

    public static function organizationInvalid(): self
    {
        return new self('الوحدة التنظيمية غير صالحة أو غير نشطة.', 'EMPLOYEE_ORGANIZATION_INVALID', 422);
    }

    public static function positionInvalid(): self
    {
        return new self('المسمى الوظيفي غير صالح أو غير نشط.', 'EMPLOYEE_POSITION_INVALID', 422);
    }

    public static function inactive(): self
    {
        return new self('الموظف غير نشط.', 'EMPLOYEE_INACTIVE', 422);
    }

    public static function positionInUse(): self
    {
        return new self('لا يمكن حذف مسمى وظيفي مرتبط بموظفين. عطّله بدلاً من الحذف.', 'POSITION_IN_USE', 422);
    }

    public static function positionNameTaken(): self
    {
        return new self('اسم المسمى الوظيفي مستخدم مسبقاً.', 'POSITION_NAME_TAKEN', 422);
    }

    public static function positionCodeTaken(): self
    {
        return new self('رمز المسمى الوظيفي مستخدم مسبقاً.', 'POSITION_CODE_TAKEN', 422);
    }

    public static function positionCodeImmutable(): self
    {
        return new self('رمز المسمى الوظيفي غير قابل للتعديل بعد الإنشاء.', 'POSITION_CODE_IMMUTABLE', 422);
    }
}
