<?php

namespace App\Modules\Accommodation\Exceptions;

use App\Core\Shared\ApiResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class AccommodationDomainException extends RuntimeException
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
            code: $this->errorCode,
            errors: $this->errors,
            status: $this->httpStatus,
        );
    }

    public static function forbidden(): self
    {
        return new self('ليست لديك صلاحية على هذه المنشأة في الموسم الحالي.', 'FORBIDDEN', 403);
    }

    public static function codeTaken(): self
    {
        return new self('الرمز مستخدم مسبقًا.', 'ACCOMMODATION_CODE_TAKEN');
    }

    public static function structureCycle(): self
    {
        return new self('لا يمكن جعل الهيكل أبًا لنفسه أو لأحد أسلافه.', 'ACCOMMODATION_STRUCTURE_CYCLE');
    }

    public static function invalidReference(): self
    {
        return new self('المرجع لا ينتمي إلى المنشأة أو الموسم المطلوب.', 'ACCOMMODATION_INVALID_REFERENCE');
    }

    public static function referenced(): self
    {
        return new self('لا يمكن الحذف لوجود سجلات مرتبطة.', 'ACCOMMODATION_REFERENCED');
    }

    public static function intervalInvalid(): self
    {
        return new self('نهاية المدة يجب أن تكون بعد بدايتها بتوقيت UTC.', 'ACCOMMODATION_INTERVAL_INVALID');
    }

    public static function granteeTenant(): self
    {
        return new self('المستخدم الممنوح يجب أن ينتمي إلى المنشأة الحالية.', 'ACCOMMODATION_GRANTEE_TENANT');
    }

    public static function scopeExists(): self
    {
        return new self('يوجد منح ساري لنفس النطاق.', 'ACCOMMODATION_SCOPE_EXISTS');
    }

    public static function alreadyRevoked(): self
    {
        return new self('هذا المنح ملغى مسبقًا.', 'ACCOMMODATION_SCOPE_REVOKED');
    }

    public static function pilgrimOverlap(): self
    {
        return new self('يوجد تسكين مؤكد متداخل لنفس الحاج في الموسم.', 'ACCOMMODATION_PILGRIM_OVERLAP');
    }

    public static function capacityConflict(string $message = 'التعارض مع السعة أو الموضع أو التخصيص الموسمي المؤكد.'): self
    {
        return new self($message, 'ACCOMMODATION_CAPACITY_CONFLICT');
    }

    public static function familyExclusivity(string $message = 'تعارض حصريّة وحدة العائلات أو مجموعة العائلة غير صالحة.'): self
    {
        return new self($message, 'ACCOMMODATION_FAMILY_EXCLUSIVITY');
    }

    public static function notDraft(): self
    {
        return new self('يمكن تأكيد المسودات فقط.', 'ACCOMMODATION_INVALID_REFERENCE');
    }

    public static function ineligiblePilgrim(): self
    {
        return new self('حالة مشاركة الحاج لا تسمح بالتأكيد.', 'ACCOMMODATION_INVALID_REFERENCE');
    }

    public static function unknownGender(): self
    {
        return new self('لا يمكن التأكيد دون معرفة جنس الحاج.', 'ACCOMMODATION_INVALID_REFERENCE');
    }

    public static function audienceMismatch(): self
    {
        return new self('جمهور الوحدة أو المنشأة لا يطابق جنس الحاج.', 'ACCOMMODATION_INVALID_REFERENCE');
    }

    public static function illegalTransition(string $message = 'انتقال حالة التسكين غير مسموح.'): self
    {
        return new self($message, 'ACCOMMODATION_ILLEGAL_TRANSITION');
    }

    public static function checkoutBeforeStart(): self
    {
        return new self(
            'لا يمكن تسجيل الخروج قبل بداية مدة التسكين.',
            'ACCOMMODATION_CHECKOUT_BEFORE_START',
        );
    }

    public static function relocateIntervalInvalid(): self
    {
        return new self(
            'وقت النقل يجب أن يقع ضمن مدة التسكين الأصلية.',
            'ACCOMMODATION_RELOCATE_INTERVAL_INVALID',
        );
    }
}
