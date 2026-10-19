<?php

namespace App\Modules\Accommodation\Support;

use App\Modules\Accommodation\Exceptions\AccommodationDomainException;
use App\Modules\Accommodation\Models\AccommodationAssignment;
use App\Modules\Accommodation\Models\AccommodationSeasonPosition;
use App\Modules\Accommodation\Models\AccommodationSeasonUnit;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Peak / position / cross-season / pilgrim / family checks for reserving stays.
 * Reserving = booking_status in (confirmed, completed) on [starts_at, effective_ends_at).
 * Callers must hold the approved lock order before invoking.
 */
final class AccommodationCapacityGuard
{
    /**
     * Half-open overlap: existing.starts < candidate.end AND existing.end > candidate.start.
     */
    public function intervalsOverlap(
        CarbonInterface $aStart,
        CarbonInterface $aEnd,
        CarbonInterface $bStart,
        CarbonInterface $bEnd,
    ): bool {
        return $aStart->lt($bEnd) && $aEnd->gt($bStart);
    }

    /**
     * @param  list<int>  $excludeAssignmentIds
     */
    public function assertPilgrimNoOverlap(
        int $seasonPilgrimId,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        array $excludeAssignmentIds = [],
    ): void {
        $rows = $this->confirmedOverlappingQuery($startsAt, $endsAt, $excludeAssignmentIds)
            ->where('season_pilgrim_id', $seasonPilgrimId)
            ->limit(1)
            ->exists();

        if ($rows) {
            throw AccommodationDomainException::pilgrimOverlap();
        }
    }

    /**
     * @param  list<int>  $excludeAssignmentIds
     */
    public function assertCrossSeasonUnitNoOverlap(
        int $unitId,
        int $contextSeasonId,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        array $excludeAssignmentIds = [],
    ): void {
        $exists = $this->confirmedOverlappingQuery($startsAt, $endsAt, $excludeAssignmentIds)
            ->where('unit_id', $unitId)
            ->where('season_id', '!=', $contextSeasonId)
            ->limit(1)
            ->exists();

        if ($exists) {
            throw AccommodationDomainException::capacityConflict(
                'يوجد تسكين مؤكد متداخل على نفس الوحدة الثابتة في موسم آخر.',
            );
        }
    }

    /**
     * @param  list<int>  $excludeAssignmentIds
     */
    public function assertSeasonUnitPeak(
        AccommodationSeasonUnit $seasonUnit,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        array $excludeAssignmentIds = [],
        int $additionalOccupants = 1,
    ): void {
        $cap = $this->effectiveCapacity($seasonUnit);
        if ($cap === null) {
            throw AccommodationDomainException::capacityConflict('سعة وحدة الموسم غير معرّفة.');
        }

        $existing = $this->confirmedOverlappingQuery($startsAt, $endsAt, $excludeAssignmentIds)
            ->where('season_unit_id', $seasonUnit->id)
            ->get(['starts_at', 'effective_ends_at']);

        $peak = $this->peakConcurrent($existing, $startsAt, $endsAt, $additionalOccupants);
        if ($peak > $cap) {
            throw AccommodationDomainException::capacityConflict('تجاوز السعة القصوى المتزامنة لوحدة الموسم.');
        }
    }

    /**
     * @param  list<int>  $excludeAssignmentIds
     */
    public function assertPositionExclusive(
        int $seasonPositionId,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        array $excludeAssignmentIds = [],
    ): void {
        $exists = $this->confirmedOverlappingQuery($startsAt, $endsAt, $excludeAssignmentIds)
            ->where('season_position_id', $seasonPositionId)
            ->limit(1)
            ->exists();

        if ($exists) {
            throw AccommodationDomainException::capacityConflict('الموضع محجوز بتسكين مؤكد متداخل.');
        }
    }

    /**
     * @param  list<int>  $excludeAssignmentIds
     */
    public function assertFamilyExclusivity(
        AccommodationSeasonUnit $seasonUnit,
        ?int $familyGroupId,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        array $excludeAssignmentIds = [],
    ): void {
        if ($seasonUnit->audience !== 'family') {
            return;
        }

        if ($familyGroupId === null || $familyGroupId <= 0) {
            throw AccommodationDomainException::familyExclusivity(
                'تأكيد وحدة العائلات يتطلب مجموعة عائلة صالحة.',
            );
        }

        $conflict = $this->confirmedOverlappingQuery($startsAt, $endsAt, $excludeAssignmentIds)
            ->where('season_unit_id', $seasonUnit->id)
            ->whereNotNull('family_group_id')
            ->where('family_group_id', '!=', $familyGroupId)
            ->limit(1)
            ->exists();

        if ($conflict) {
            throw AccommodationDomainException::familyExclusivity(
                'لا يمكن تداخل مجموعات عائلات مختلفة في نفس وحدة العائلات.',
            );
        }
    }

    public function effectiveCapacity(AccommodationSeasonUnit $seasonUnit): ?int
    {
        if ($seasonUnit->allocation_mode === 'position') {
            $enabled = AccommodationSeasonPosition::query()
                ->where('season_unit_id', $seasonUnit->id)
                ->where('is_active', true)
                ->count();

            if ($enabled < 1) {
                return 0;
            }

            $ceiling = $seasonUnit->capacity;

            return $ceiling === null ? $enabled : min($enabled, max(0, (int) $ceiling));
        }

        return $seasonUnit->capacity === null ? null : max(0, (int) $seasonUnit->capacity);
    }

    /**
     * Peak concurrent occupancy if candidate (additionalOccupants over [starts, ends)) is added.
     *
     * @param  Collection<int, AccommodationAssignment>  $existing
     */
    public function peakConcurrent(
        Collection $existing,
        CarbonInterface $candidateStart,
        CarbonInterface $candidateEnd,
        int $additionalOccupants = 1,
    ): int {
        /** @var list<array{0: int, 1: int}> $events */
        $events = [];

        foreach ($existing as $row) {
            $events[] = [$row->starts_at->getTimestamp(), 1];
            $events[] = [$row->effective_ends_at->getTimestamp(), -1];
        }

        for ($i = 0; $i < $additionalOccupants; $i++) {
            $events[] = [$candidateStart->getTimestamp(), 1];
            $events[] = [$candidateEnd->getTimestamp(), -1];
        }

        usort($events, static function (array $a, array $b): int {
            if ($a[0] !== $b[0]) {
                return $a[0] <=> $b[0];
            }

            // Half-open: process departures before arrivals at the same instant.
            return $a[1] <=> $b[1];
        });

        $current = 0;
        $peak = 0;
        foreach ($events as [$time, $delta]) {
            $current += $delta;
            if ($current > $peak) {
                $peak = $current;
            }
        }

        return $peak;
    }

    /**
     * @param  list<int>  $excludeAssignmentIds
     */
    public function assertCapacityDecreaseAllowed(AccommodationSeasonUnit $seasonUnit, ?int $newCapacity): void
    {
        $reserving = $this->reservingOnSeasonUnit($seasonUnit->id);

        if ($reserving->isEmpty()) {
            return;
        }

        if ($newCapacity === null && $seasonUnit->allocation_mode === 'capacity') {
            throw AccommodationDomainException::capacityConflict(
                'لا يمكن إزالة السعة مع وجود تسكينات مؤكدة.',
            );
        }

        $probe = $seasonUnit->newInstance($seasonUnit->getAttributes());
        $probe->capacity = $newCapacity;
        $effective = $this->effectiveCapacity($probe);
        if ($effective === null) {
            throw AccommodationDomainException::capacityConflict('سعة وحدة الموسم غير معرّفة.');
        }

        $peak = 0;
        foreach ($reserving as $row) {
            $local = $this->peakConcurrent($reserving, $row->starts_at, $row->effective_ends_at, 0);
            $peak = max($peak, $local);
        }

        if ($peak > $effective) {
            throw AccommodationDomainException::capacityConflict(
                'تخفيض السعة يتعارض مع تسكينات مؤكدة قائمة.',
            );
        }
    }

    public function assertEnabledPositionDecreaseAllowed(AccommodationSeasonUnit $seasonUnit, int $disabledPositionId): void
    {
        $this->assertNoConfirmedOnPosition($disabledPositionId);

        $reserving = $this->reservingOnSeasonUnit($seasonUnit->id);

        if ($reserving->isEmpty() || $seasonUnit->allocation_mode !== 'position') {
            return;
        }

        $enabled = AccommodationSeasonPosition::query()
            ->where('season_unit_id', $seasonUnit->id)
            ->where('is_active', true)
            ->where('id', '!=', $disabledPositionId)
            ->count();

        $ceiling = $seasonUnit->capacity;
        $effective = $ceiling === null ? $enabled : min($enabled, max(0, (int) $ceiling));

        $peak = 0;
        foreach ($reserving as $row) {
            $local = $this->peakConcurrent($reserving, $row->starts_at, $row->effective_ends_at, 0);
            $peak = max($peak, $local);
        }

        if ($peak > $effective) {
            throw AccommodationDomainException::capacityConflict(
                'تعطيل الموضع يخفض السعة التشغيلية دون التسكينات المؤكدة.',
            );
        }
    }

    public function assertNoConfirmedOnPosition(int $seasonPositionId): void
    {
        // Active reservations only — completed history does not block deactivation.
        $exists = AccommodationAssignment::query()
            ->where('season_position_id', $seasonPositionId)
            ->where('booking_status', 'confirmed')
            ->orderBy('id')
            ->lockForUpdate()
            ->limit(1)
            ->exists();

        if ($exists) {
            throw AccommodationDomainException::capacityConflict(
                'تعطيل أو حذف الموضع يتعارض مع تسكين مؤكد.',
            );
        }
    }

    public function assertNoConfirmedOnSeasonUnit(int $seasonUnitId): void
    {
        $exists = AccommodationAssignment::query()
            ->where('season_unit_id', $seasonUnitId)
            ->where('booking_status', 'confirmed')
            ->orderBy('id')
            ->lockForUpdate()
            ->limit(1)
            ->exists();

        if ($exists) {
            throw AccommodationDomainException::capacityConflict(
                'تعديل أو حذف تخصيص الوحدة يتعارض مع تسكين مؤكد.',
            );
        }
    }

    /**
     * Locking read so concurrent writers see the latest committed rows under
     * InnoDB REPEATABLE READ (plain SELECT would reuse the TX snapshot).
     *
     * @param  list<int>  $excludeAssignmentIds
     * @return \Illuminate\Database\Eloquent\Builder<AccommodationAssignment>
     */
    private function confirmedOverlappingQuery(
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        array $excludeAssignmentIds,
    ) {
        $query = AccommodationAssignment::query()
            ->whereIn('booking_status', ['confirmed', 'completed'])
            ->where('starts_at', '<', $endsAt)
            ->where('effective_ends_at', '>', $startsAt)
            ->orderBy('id')
            ->lockForUpdate();

        if ($excludeAssignmentIds !== []) {
            $query->whereNotIn('id', $excludeAssignmentIds);
        }

        return $query;
    }

    /**
     * @return Collection<int, AccommodationAssignment>
     */
    private function reservingOnSeasonUnit(int $seasonUnitId): Collection
    {
        return AccommodationAssignment::query()
            ->where('season_unit_id', $seasonUnitId)
            ->whereIn('booking_status', ['confirmed', 'completed'])
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id', 'starts_at', 'effective_ends_at']);
    }
}
