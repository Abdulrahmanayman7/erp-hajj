<?php

namespace App\Modules\SeasonalTemplates\Exceptions;

use App\Core\Shared\ApiResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class SeasonalTemplatesDomainException extends RuntimeException
{
    public const PREVIEW_STALE = 'SEASON_COPY_PREVIEW_STALE';

    public function __construct(
        string $message,
        private readonly string $errorCode,
        private readonly int $httpStatus = 422,
        private readonly string $invalidationReason = 'STALE',
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

    public function invalidationReason(): string
    {
        return $this->invalidationReason;
    }

    public function render(): JsonResponse
    {
        return ApiResponse::error(
            message: $this->getMessage(),
            code: $this->errorCode(),
            status: $this->status(),
        );
    }

    public static function targetNotDraft(): self
    {
        return new self('الموسم الهدف يجب أن يكون في حالة مسودة.', 'SEASON_COPY_TARGET_NOT_DRAFT', 422);
    }

    public static function sourceInvalid(string $message = 'مصدر النسخ غير صالح.'): self
    {
        return new self($message, 'SEASON_COPY_SOURCE_INVALID', 422);
    }

    public static function previewStale(string $reason = 'STALE'): self
    {
        return new self(
            'المعاينة لم تعد صالحة. أنشئ معاينة جديدة قبل التنفيذ.',
            self::PREVIEW_STALE,
            409,
            $reason,
        );
    }

    public static function kindDenied(string $kind): self
    {
        return new self(
            'نوع البيانات غير مصرح بنسخه: '.mb_substr($kind, 0, 64),
            'SEASON_COPY_KIND_DENIED',
            422,
        );
    }

    public static function conflictUnresolved(string $message = 'توجد تعارضات غير محسومة. حدد قراراً لكل تعارض.'): self
    {
        return new self($message, 'SEASON_COPY_CONFLICT_UNRESOLVED', 422);
    }

    public static function previewRequired(): self
    {
        return new self('المعاينة مطلوبة قبل تنفيذ النسخ.', 'SEASON_COPY_PREVIEW_REQUIRED', 422);
    }

    public static function invalidTransition(string $message = 'انتقال حالة القالب غير مسموح.'): self
    {
        return new self($message, 'SEASON_TEMPLATE_INVALID_TRANSITION', 422);
    }

    public static function snapshotInvalid(string $message): self
    {
        return new self($message, 'SEASON_TEMPLATE_SNAPSHOT_INVALID', 422);
    }

    /**
     * A kind without a template snapshot needs a live source season (hybrid fallback).
     */
    public static function sourceRequired(): self
    {
        return new self('الموسم المصدر مطلوب لأن القالب لا يحتوي لقطة كاملة لكل الأنواع المحددة.', 'SEASON_COPY_SOURCE_REQUIRED', 422);
    }

    /**
     * Snapshot-only kinds (e.g. contract definitions) cannot be copied without a snapshot.
     */
    public static function snapshotRequired(string $kind): self
    {
        return new self(
            'هذا النوع يتطلب لقطة في القالب ولا يُنسخ من موسم: '.mb_substr($kind, 0, 64),
            'SEASON_COPY_SNAPSHOT_REQUIRED',
            422,
        );
    }

    public static function numberUnavailable(): self
    {
        return new self('تعذر إصدار الرقم المرجعي. أعد المحاولة.', 'SEASON_NUMBER_UNAVAILABLE', 409);
    }
}
