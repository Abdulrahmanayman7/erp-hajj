<?php

namespace App\Core\Authorization\Exceptions;

use App\Core\Shared\ApiResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class AuthorizationDomainException extends RuntimeException
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

    public static function forbidden(string $message = 'ليس لديك صلاحية لتنفيذ هذا الإجراء.'): self
    {
        return new self($message, 'AUTHORIZATION_DENIED', 403);
    }

    public static function userNotFound(): self
    {
        return new self('المستخدم غير موجود.', 'USER_NOT_FOUND', 404);
    }

    public static function roleNotFound(): self
    {
        return new self('الدور غير موجود.', 'ROLE_NOT_FOUND', 404);
    }

    public static function lastOwnerProtected(string $code = 'USER_LAST_OWNER_PROTECTED'): self
    {
        return new self('لا يمكن إزالة آخر مالك منشأة فعّال.', $code, 422);
    }

    public static function selfDisableForbidden(): self
    {
        return new self('لا يمكنك تعطيل حسابك بنفسك.', 'USER_SELF_DISABLE_FORBIDDEN', 422);
    }

    public static function permissionAssignmentForbidden(): self
    {
        return new self('لا يمكن منح صلاحيات تتجاوز صلاحياتك.', 'PERMISSION_ASSIGNMENT_FORBIDDEN', 422);
    }

    public static function roleSystemProtected(): self
    {
        return new self('هذا الدور نظامي ومحمي.', 'ROLE_SYSTEM_PROTECTED', 422);
    }

    public static function roleInUse(): self
    {
        return new self('لا يمكن حذف دور مرتبط بمستخدمين.', 'ROLE_IN_USE', 422);
    }

    public static function roleInactive(): self
    {
        return new self('لا يمكن تعيين دور غير مفعّل.', 'ROLE_INACTIVE', 422);
    }

    public static function roleLastOwnerProtected(): self
    {
        return new self('دور مالك المنشأة محمي ولا يمكن تعطيله أو حذفه.', 'ROLE_LAST_OWNER_PROTECTED', 422);
    }

    public static function permissionNotFound(): self
    {
        return new self('صلاحية غير موجودة.', 'PERMISSION_NOT_FOUND', 422);
    }

    public static function selfRoleEscalationForbidden(): self
    {
        return new self('لا يمكن تصعيد صلاحياتك عبر تعيين الأدوار.', 'USER_SELF_ROLE_ESCALATION_FORBIDDEN', 422);
    }
}
