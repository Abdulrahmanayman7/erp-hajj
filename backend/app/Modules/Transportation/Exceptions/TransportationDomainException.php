<?php

namespace App\Modules\Transportation\Exceptions;

use App\Core\Shared\ApiResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class TransportationDomainException extends RuntimeException
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

    public function status(): int
    {
        return $this->httpStatus;
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
            code: $this->errorCode(),
            errors: $this->errors,
            status: $this->status(),
        );
    }

    // ─── Registry ────────────────────────────────────────────────────────────

    public static function pointCodeTaken(): self
    {
        return new self('رمز نقطة النقل مستخدم مسبقًا.', 'TRANSPORT_POINT_CODE_TAKEN');
    }

    public static function pointInactive(): self
    {
        return new self('نقطة النقل غير نشطة.', 'TRANSPORT_POINT_INACTIVE');
    }

    public static function pointInUse(): self
    {
        return new self('لا يمكن حذف نقطة نقل مستخدمة في حركات. يمكن إيقافها بدلًا من ذلك.', 'TRANSPORT_POINT_IN_USE');
    }

    public static function pointsSame(): self
    {
        return new self('نقطة الانطلاق ونقطة الوصول يجب أن تكونا مختلفتين.', 'TRANSPORT_POINTS_SAME');
    }

    public static function vehiclePlateTaken(): self
    {
        return new self('رقم اللوحة مسجل لمركبة أخرى.', 'TRANSPORT_VEHICLE_PLATE_TAKEN');
    }

    public static function vehicleAssetTaken(): self
    {
        return new self('الأصل مرتبط بمركبة أخرى.', 'TRANSPORT_VEHICLE_ASSET_TAKEN');
    }

    public static function vehicleInactive(): self
    {
        return new self('المركبة غير نشطة.', 'TRANSPORT_VEHICLE_INACTIVE');
    }

    public static function vehicleInUse(): self
    {
        return new self('لا يمكن حذف مركبة مسجلة في أسطول موسمي. يمكن إيقافها بدلًا من ذلك.', 'TRANSPORT_VEHICLE_IN_USE');
    }

    public static function numberUnavailable(): self
    {
        return new self('تعذر إصدار الرقم المرجعي. أعد المحاولة.', 'TRANSPORT_NUMBER_UNAVAILABLE', 409);
    }

    public static function externalDriverInactive(): self
    {
        return new self('السائق الخارجي غير نشط.', 'TRANSPORT_EXTERNAL_DRIVER_INACTIVE');
    }

    public static function externalDriverInUse(): self
    {
        return new self('لا يمكن حذف سائق خارجي مرتبط بمركبات أو رحلات. يمكن إيقافه بدلًا من ذلك.', 'TRANSPORT_EXTERNAL_DRIVER_IN_USE');
    }

    public static function linkInvalid(string $field): self
    {
        return new self('المرجع المحدد غير موجود.', 'TRANSPORT_LINK_INVALID', 422, [$field => ['المرجع المحدد غير موجود.']]);
    }

    // ─── Season fleet / entitlements ─────────────────────────────────────────

    public static function seasonVehicleDuplicate(): self
    {
        return new self('المركبة مسجلة مسبقًا في أسطول هذا الموسم.', 'TRANSPORT_SEASON_VEHICLE_DUPLICATE');
    }

    public static function seasonVehicleInactive(): self
    {
        return new self('مركبة الموسم غير نشطة.', 'TRANSPORT_SEASON_VEHICLE_INACTIVE');
    }

    public static function seasonVehicleInUse(): self
    {
        return new self('لا يمكن حذف مركبة موسم مستخدمة في رحلات. يمكن إيقافها بدلًا من ذلك.', 'TRANSPORT_SEASON_VEHICLE_IN_USE');
    }

    public static function capacityBelowAssigned(): self
    {
        return new self('لا يمكن خفض السعة دون عدد الركاب المعيّنين أو السعة المخصصة لرحلة قائمة.', 'TRANSPORT_CAPACITY_BELOW_ASSIGNED');
    }

    public static function employeeInvalid(string $field): self
    {
        return new self('الموظف غير موجود أو غير نشط.', 'TRANSPORT_EMPLOYEE_INVALID', 422, [$field => ['الموظف غير موجود أو غير نشط.']]);
    }

    public static function driverInvalid(): self
    {
        return new self('حدد سائقًا واحدًا: موظفًا أو سائقًا خارجيًا.', 'TRANSPORT_DRIVER_INVALID');
    }

    public static function entitlementDuplicate(): self
    {
        return new self('للبرنامج استحقاق نقل مسجل مسبقًا.', 'TRANSPORT_ENTITLEMENT_DUPLICATE');
    }

    // ─── Movement / run ──────────────────────────────────────────────────────

    public static function movementInvalidTransition(): self
    {
        return new self('انتقال حالة الحركة غير مسموح.', 'TRANSPORT_MOVEMENT_INVALID_TRANSITION');
    }

    public static function movementNotEditable(): self
    {
        return new self('لا يمكن تعديل الحركة في حالتها الحالية.', 'TRANSPORT_MOVEMENT_NOT_EDITABLE');
    }

    public static function movementRunsNotSettled(): self
    {
        return new self('لا يمكن إنهاء الحركة قبل إنهاء أو إلغاء جميع رحلاتها.', 'TRANSPORT_MOVEMENT_RUNS_NOT_SETTLED');
    }

    public static function runInvalidTransition(): self
    {
        return new self('انتقال حالة الرحلة غير مسموح.', 'TRANSPORT_RUN_INVALID_TRANSITION');
    }

    public static function runNotEditable(): self
    {
        return new self('لا يمكن تعديل الرحلة في حالتها الحالية.', 'TRANSPORT_RUN_NOT_EDITABLE');
    }

    public static function runDriverRequired(): self
    {
        return new self('لا يمكن انطلاق الرحلة دون سائق.', 'TRANSPORT_RUN_DRIVER_REQUIRED');
    }

    public static function runHasBoardedPassengers(): self
    {
        return new self('لا يمكن إلغاء رحلة صعد إليها ركاب.', 'TRANSPORT_RUN_HAS_BOARDED_PASSENGERS');
    }

    public static function runPassengersNotSettled(): self
    {
        return new self('لا يمكن إنهاء الرحلة قبل تسجيل وصول أو غياب أو إلغاء جميع الركاب.', 'TRANSPORT_RUN_PASSENGERS_NOT_SETTLED');
    }

    public static function runCapacityOverrideInvalid(): self
    {
        return new self('السعة المخصصة للرحلة يجب ألا تتجاوز سعة المركبة وألا تقل عن عدد الركاب المعيّنين.', 'TRANSPORT_RUN_CAPACITY_OVERRIDE_INVALID');
    }

    public static function vehicleOverlap(): self
    {
        return new self('المركبة مكلفة برحلة أخرى في الفترة نفسها.', 'TRANSPORT_VEHICLE_OVERLAP');
    }

    public static function driverOverlap(): self
    {
        return new self('السائق مكلف برحلة أخرى في الفترة نفسها.', 'TRANSPORT_DRIVER_OVERLAP');
    }

    public static function substitutionReasonRequired(): self
    {
        return new self('يجب ذكر سبب الاستبدال بعد بدء تنفيذ الرحلة.', 'TRANSPORT_SUBSTITUTION_REASON_REQUIRED', 422, [
            'reason' => ['يجب ذكر سبب الاستبدال بعد بدء تنفيذ الرحلة.'],
        ]);
    }

    // ─── Passengers ──────────────────────────────────────────────────────────

    /**
     * @param  list<int>  $seasonPilgrimIds
     */
    public static function passengerNotEligible(array $seasonPilgrimIds): self
    {
        return new self('يمكن تعيين مشاركات الحجاج النشطة في هذا الموسم فقط.', 'TRANSPORT_PASSENGER_NOT_ELIGIBLE', 422, [
            'season_pilgrim_ids' => $seasonPilgrimIds,
        ]);
    }

    /**
     * @param  list<int>  $seasonPilgrimIds
     */
    public static function passengerDuplicate(array $seasonPilgrimIds): self
    {
        return new self('الحاج معيّن مسبقًا على هذه الرحلة.', 'TRANSPORT_PASSENGER_DUPLICATE', 422, [
            'season_pilgrim_ids' => $seasonPilgrimIds,
        ]);
    }

    public static function passengersNoneEligible(): self
    {
        return new self('لا يوجد أعضاء مؤهلون للتعيين في المجموعة المحددة.', 'TRANSPORT_PASSENGERS_NONE_ELIGIBLE');
    }

    /**
     * @param  list<int>  $seasonPilgrimIds
     */
    public static function capacityExceeded(int $capacity, int $occupied, int $requested, array $seasonPilgrimIds = []): self
    {
        $errors = [
            'capacity' => $capacity,
            'occupied' => $occupied,
            'requested' => $requested,
        ];
        if ($seasonPilgrimIds !== []) {
            $errors['season_pilgrim_ids'] = $seasonPilgrimIds;
        }

        return new self('تتجاوز الإضافة سعة الرحلة.', 'TRANSPORT_CAPACITY_EXCEEDED', 422, $errors);
    }

    /**
     * @param  list<int>  $passengerIds
     */
    public static function passengerInvalidTransition(array $passengerIds = []): self
    {
        return new self('انتقال حالة الراكب غير مسموح.', 'TRANSPORT_PASSENGER_INVALID_TRANSITION', 422, $passengerIds === [] ? [] : [
            'passenger_ids' => $passengerIds,
        ]);
    }

    public static function manifestLocked(): self
    {
        return new self('لا تقبل الرحلة تعديل كشف الركاب في حالتها الحالية.', 'TRANSPORT_MANIFEST_LOCKED');
    }

    public static function sourceInvalid(): self
    {
        return new self('مصدر التعيين غير موجود في هذا الموسم.', 'TRANSPORT_SOURCE_INVALID');
    }

    // ─── Entitlement ─────────────────────────────────────────────────────────

    /**
     * @param  list<int>  $seasonPilgrimIds
     */
    public static function entitlementMismatch(array $seasonPilgrimIds): self
    {
        return new self('المركبة لا تطابق استحقاق النقل لبرنامج الحاج.', 'TRANSPORT_ENTITLEMENT_MISMATCH', 422, [
            'season_pilgrim_ids' => $seasonPilgrimIds,
        ]);
    }

    public static function entitlementOverrideForbidden(): self
    {
        return new self('لا تملك صلاحية تجاوز استحقاق النقل.', 'TRANSPORT_ENTITLEMENT_OVERRIDE_FORBIDDEN', 403);
    }

    public static function entitlementOverrideNotNeeded(): self
    {
        return new self('لا يوجد عدم تطابق في الاستحقاق يتطلب التجاوز.', 'TRANSPORT_ENTITLEMENT_OVERRIDE_NOT_NEEDED');
    }

    // ─── Timeline / reviews ──────────────────────────────────────────────────

    public static function occurredAtFuture(): self
    {
        return new self('لا يمكن تسجيل حدث بوقت مستقبلي.', 'TRANSPORT_OCCURRED_AT_FUTURE', 422, [
            'occurred_at' => ['لا يمكن تسجيل حدث بوقت مستقبلي.'],
        ]);
    }

    public static function backdateReasonRequired(): self
    {
        return new self('يجب ذكر سبب تسجيل الحدث بتاريخ سابق.', 'TRANSPORT_BACKDATE_REASON_REQUIRED', 422, [
            'reason' => ['يجب ذكر سبب تسجيل الحدث بتاريخ سابق.'],
        ]);
    }

    public static function backdateForbidden(): self
    {
        return new self('لا تملك صلاحية تسجيل الأحداث بتاريخ سابق.', 'TRANSPORT_BACKDATE_FORBIDDEN', 403);
    }

    public static function timelineEventNotCorrectable(): self
    {
        return new self('لا يمكن تصحيح هذا النوع من الأحداث.', 'TRANSPORT_TIMELINE_EVENT_NOT_CORRECTABLE');
    }

    public static function timelineEventAlreadyCorrected(): self
    {
        return new self('تم تصحيح هذا الحدث مسبقًا. صحّح أحدث تصحيح بدلًا منه.', 'TRANSPORT_TIMELINE_EVENT_ALREADY_CORRECTED');
    }

    public static function reviewAlreadyResolved(): self
    {
        return new self('بند المراجعة مغلق مسبقًا.', 'TRANSPORT_REVIEW_ALREADY_RESOLVED');
    }

    // ─── Breakdown / replacement (ADR-0021 D03; aligns with T7) ─────────────

    public static function breakdownNotOpen(): self
    {
        return new self('العطل ليس مفتوحًا ولا يقبل هذه العملية.', 'TRANSPORT_BREAKDOWN_NOT_OPEN');
    }

    public static function breakdownVehicleMismatch(): self
    {
        return new self('مركبة العطل لم تعد المركبة الحالية للرحلة.', 'TRANSPORT_BREAKDOWN_VEHICLE_MISMATCH');
    }

    public static function breakdownRunTerminal(): self
    {
        return new self('لا يمكن تسجيل عطل أو استبدال على رحلة منتهية.', 'TRANSPORT_BREAKDOWN_RUN_TERMINAL');
    }

    public static function replacementCapacityPlanRequired(int $gap): self
    {
        return new self(
            'سعة البديل أقل من الركاب؛ يلزم خطة إعادة توزيع صريحة.',
            'TRANSPORT_REPLACEMENT_CAPACITY_PLAN_REQUIRED',
            422,
            ['capacity_gap' => $gap],
        );
    }

    /**
     * @param  list<int>  $passengerIds
     */
    public static function replacementPlanInvalid(array $passengerIds = []): self
    {
        return new self('خطة إعادة توزيع الركاب غير صالحة.', 'TRANSPORT_REPLACEMENT_PLAN_INVALID', 422, $passengerIds === [] ? [] : [
            'run_passenger_ids' => $passengerIds,
        ]);
    }

    public static function replacementBoardedExceedCapacity(): self
    {
        return new self(
            'عدد الركاب الذين صعدوا يتجاوز سعة البديل ولا يمكن نقلهم في هذه العملية.',
            'TRANSPORT_REPLACEMENT_BOARDED_EXCEED_CAPACITY',
        );
    }

    public static function replacementTargetRunInvalid(): self
    {
        return new self('الرحلة المستهدفة لإعادة التوزيع غير مؤهلة.', 'TRANSPORT_REPLACEMENT_TARGET_RUN_INVALID');
    }

    public static function replacementSameVehicle(): self
    {
        return new self('المركبة البديلة يجب أن تختلف عن المركبة المتضررة.', 'TRANSPORT_REPLACEMENT_SAME_VEHICLE');
    }
}
