<?php

namespace App\Modules\Accommodation\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Models\User;
use App\Modules\Accommodation\Exceptions\AccommodationDomainException;
use App\Modules\Accommodation\Models\AccommodationAssignment;
use App\Modules\Accommodation\Models\AccommodationEvent;
use App\Modules\Accommodation\Models\AccommodationSeasonFacility;
use App\Modules\Accommodation\Models\AccommodationSeasonPosition;
use App\Modules\Accommodation\Models\AccommodationSeasonUnit;
use App\Modules\Accommodation\Support\AccommodationAccess;
use App\Modules\Accommodation\Support\AccommodationAudit;
use App\Modules\Accommodation\Support\AccommodationCapacityGuard;
use App\Modules\Accommodation\Support\AccommodationEventWriter;
use App\Modules\Accommodation\Support\AccommodationLockOrder;
use App\Modules\Accommodation\Support\AccommodationPersistence;
use App\Modules\Accommodation\Support\AccommodationTransaction;
use App\Modules\SeasonPilgrims\Context\SeasonContext;
use App\Modules\SeasonPilgrims\Enums\PilgrimGender;
use App\Modules\SeasonPilgrims\Enums\SeasonPilgrimStatus;
use App\Modules\SeasonPilgrims\Models\SeasonFamilyGroup;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrim;
use App\Modules\SeasonPilgrims\Support\SeasonGuard;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;

/**
 * Sprint 3 Gate C operational writes (arrive / check-in / check-out / cancel / relocate).
 */
final class AccommodationOperationsActions
{
    private const SOURCE_STAFF = 'staff';

    public function __construct(
        private readonly AccommodationAudit $audit,
        private readonly AccommodationAccess $access,
        private readonly AccommodationLockOrder $locks,
        private readonly AccommodationCapacityGuard $capacity,
        private readonly AccommodationEventWriter $events,
        private readonly SeasonContext $seasons,
    ) {}

    public function arrive(User $actor, AccommodationAssignment $assignment, Request $request, ?string $occurredAt = null): AccommodationAssignment
    {
        return AccommodationTransaction::run(function () use ($actor, $assignment, $request, $occurredAt): AccommodationAssignment {
            $season = $this->seasons->require();
            SeasonGuard::assertActive($season);
            $this->assertVerb($actor, 'accommodation.arrive');

            $locked = $this->lockAssignmentForOps($season->id, (int) $assignment->id);
            $ops = $this->effectiveOps($locked);

            if ($locked->booking_status !== 'confirmed') {
                throw AccommodationDomainException::illegalTransition();
            }
            if ($ops === 'arrived') {
                return $locked->fresh();
            }
            if (! in_array($ops, ['assigned'], true)) {
                throw AccommodationDomainException::illegalTransition();
            }

            $seasonUnit = $this->locks->lockSeasonUnits([(int) $locked->season_unit_id])->first();
            if ($seasonUnit === null) {
                throw AccommodationDomainException::invalidReference();
            }
            $this->access->lockAndAssert($actor, (int) $seasonUnit->facility_id);

            $when = $this->parseOccurredAt($occurredAt);
            $locked->fill(['operational_status' => 'arrived']);
            AccommodationPersistence::save(fn () => $locked->save());

            $this->events->appendOnce(
                (int) $season->id,
                (int) $locked->id,
                AccommodationEvent::TYPE_ARRIVED,
                $when,
                $actor->id,
                self::SOURCE_STAFF,
                $this->idemKey('arrived', (int) $locked->id, $when),
            );

            $this->audit->record(
                AuthorizationSecurityEvent::ACCOMMODATION_ASSIGNMENT_ARRIVED,
                $actor->id,
                'accommodation_assignment',
                $locked->id,
                null,
                null,
                ['after_values' => ['operational_status' => 'arrived', 'occurred_at' => $when->utc()->toIso8601String()]],
                $request,
            );

            return $locked->fresh();
        });
    }

    public function checkIn(User $actor, AccommodationAssignment $assignment, Request $request, ?string $occurredAt = null): AccommodationAssignment
    {
        return AccommodationTransaction::run(function () use ($actor, $assignment, $request, $occurredAt): AccommodationAssignment {
            $season = $this->seasons->require();
            SeasonGuard::assertActive($season);
            $this->assertVerb($actor, 'accommodation.check_in');

            $locked = $this->lockAssignmentForOps($season->id, (int) $assignment->id);
            $ops = $this->effectiveOps($locked);

            if ($locked->booking_status !== 'confirmed') {
                throw AccommodationDomainException::illegalTransition();
            }
            if ($ops === 'checked_in') {
                return $locked->fresh();
            }
            if (! in_array($ops, ['assigned', 'arrived'], true)) {
                throw AccommodationDomainException::illegalTransition();
            }

            $seasonUnit = $this->locks->lockSeasonUnits([(int) $locked->season_unit_id])->first();
            if ($seasonUnit === null) {
                throw AccommodationDomainException::invalidReference();
            }
            $this->access->lockAndAssert($actor, (int) $seasonUnit->facility_id);

            $when = $this->parseOccurredAt($occurredAt);
            $locked->fill(['operational_status' => 'checked_in']);
            AccommodationPersistence::save(fn () => $locked->save());

            $this->events->appendOnce(
                (int) $season->id,
                (int) $locked->id,
                AccommodationEvent::TYPE_CHECKED_IN,
                $when,
                $actor->id,
                self::SOURCE_STAFF,
                $this->idemKey('checked_in', (int) $locked->id, $when),
            );

            $this->audit->record(
                AuthorizationSecurityEvent::ACCOMMODATION_ASSIGNMENT_CHECKED_IN,
                $actor->id,
                'accommodation_assignment',
                $locked->id,
                null,
                null,
                ['after_values' => ['operational_status' => 'checked_in', 'occurred_at' => $when->utc()->toIso8601String()]],
                $request,
            );

            return $locked->fresh();
        });
    }

    public function checkOut(User $actor, AccommodationAssignment $assignment, Request $request, ?string $occurredAt = null): AccommodationAssignment
    {
        return AccommodationTransaction::run(function () use ($actor, $assignment, $request, $occurredAt): AccommodationAssignment {
            $season = $this->seasons->require();
            SeasonGuard::assertActive($season);
            $this->assertVerb($actor, 'accommodation.check_out');

            $locked = $this->lockAssignmentForOps($season->id, (int) $assignment->id);

            if ($locked->booking_status === 'completed' && $this->effectiveOps($locked) === 'checked_out') {
                return $locked->fresh();
            }

            if ($locked->booking_status !== 'confirmed' || $this->effectiveOps($locked) !== 'checked_in') {
                throw AccommodationDomainException::illegalTransition();
            }

            $seasonUnit = $this->locks->lockSeasonUnits([(int) $locked->season_unit_id])->first();
            if ($seasonUnit === null) {
                throw AccommodationDomainException::invalidReference();
            }
            $this->access->lockAndAssert($actor, (int) $seasonUnit->facility_id);
            $this->locks->lockUnits([(int) $locked->unit_id]);

            $when = $this->parseOccurredAt($occurredAt);
            if ($when->lt($locked->starts_at)) {
                throw AccommodationDomainException::checkoutBeforeStart();
            }

            $effectiveEnd = $when->lt($locked->original_ends_at) ? $when->copy() : $locked->original_ends_at->copy();
            if (! $effectiveEnd->gt($locked->starts_at)) {
                throw AccommodationDomainException::intervalInvalid();
            }

            $locked->fill([
                'booking_status' => 'completed',
                'operational_status' => 'checked_out',
                'checkout_source' => self::SOURCE_STAFF,
                'effective_ends_at' => $effectiveEnd,
                'actual_departed_at' => $when,
            ]);
            AccommodationPersistence::save(fn () => $locked->save());

            $this->events->appendOnce(
                (int) $season->id,
                (int) $locked->id,
                AccommodationEvent::TYPE_CHECKED_OUT,
                $when,
                $actor->id,
                self::SOURCE_STAFF,
                $this->idemKey('checked_out', (int) $locked->id, $when),
                [
                    'effective_ends_at' => $effectiveEnd->utc()->toIso8601String(),
                    'original_ends_at' => $locked->original_ends_at->utc()->toIso8601String(),
                ],
            );

            $this->audit->record(
                AuthorizationSecurityEvent::ACCOMMODATION_ASSIGNMENT_CHECKED_OUT,
                $actor->id,
                'accommodation_assignment',
                $locked->id,
                null,
                null,
                [
                    'after_values' => [
                        'booking_status' => 'completed',
                        'operational_status' => 'checked_out',
                        'effective_ends_at' => $effectiveEnd->utc()->toIso8601String(),
                        'actual_departed_at' => $when->utc()->toIso8601String(),
                        'checkout_source' => self::SOURCE_STAFF,
                    ],
                ],
                $request,
            );

            return $locked->fresh();
        });
    }

    public function cancel(User $actor, AccommodationAssignment $assignment, Request $request, ?string $reason = null): AccommodationAssignment
    {
        return AccommodationTransaction::run(function () use ($actor, $assignment, $request, $reason): AccommodationAssignment {
            $season = $this->seasons->require();
            SeasonGuard::assertActive($season);
            $this->assertVerb($actor, 'accommodation.cancel_assignment');

            $locked = $this->lockAssignmentForOps($season->id, (int) $assignment->id);

            if ($locked->booking_status === 'cancelled') {
                return $locked->fresh();
            }

            if ($locked->booking_status !== 'confirmed') {
                throw AccommodationDomainException::illegalTransition();
            }

            $ops = $this->effectiveOps($locked);
            if ($ops === 'checked_in' || $ops === 'checked_out') {
                throw AccommodationDomainException::illegalTransition(
                    'لا يمكن إلغاء التسكين بعد تسجيل الدخول؛ استخدم تسجيل الخروج.',
                );
            }

            $seasonUnit = $this->locks->lockSeasonUnits([(int) $locked->season_unit_id])->first();
            if ($seasonUnit === null) {
                throw AccommodationDomainException::invalidReference();
            }
            $this->access->lockAndAssert($actor, (int) $seasonUnit->facility_id);
            $this->locks->lockUnits([(int) $locked->unit_id]);

            $when = now('UTC');
            $locked->fill(['booking_status' => 'cancelled']);
            AccommodationPersistence::save(fn () => $locked->save());

            $this->events->appendOnce(
                (int) $season->id,
                (int) $locked->id,
                AccommodationEvent::TYPE_CANCELLED,
                $when,
                $actor->id,
                self::SOURCE_STAFF,
                $this->idemKey('cancelled', (int) $locked->id, $when),
                $reason !== null && $reason !== '' ? ['reason' => $reason] : null,
            );

            $this->audit->record(
                AuthorizationSecurityEvent::ACCOMMODATION_ASSIGNMENT_CANCELLED,
                $actor->id,
                'accommodation_assignment',
                $locked->id,
                null,
                null,
                [
                    'after_values' => [
                        'booking_status' => 'cancelled',
                        'operational_status' => $locked->operational_status,
                        'reason' => $reason,
                    ],
                ],
                $request,
            );

            return $locked->fresh();
        });
    }

    /**
     * @return array{source: AccommodationAssignment, destination: AccommodationAssignment}
     */
    public function relocate(
        User $actor,
        AccommodationAssignment $assignment,
        int $destinationSeasonUnitId,
        ?int $destinationSeasonPositionId,
        Request $request,
        ?string $occurredAt = null,
        bool $checkInAfter = false,
    ): array {
        return AccommodationTransaction::run(function () use (
            $actor,
            $assignment,
            $destinationSeasonUnitId,
            $destinationSeasonPositionId,
            $request,
            $occurredAt,
            $checkInAfter,
        ): array {
            $season = $this->seasons->require();
            SeasonGuard::assertActive($season);
            $this->assertVerb($actor, 'accommodation.relocate');

            $source = $this->lockAssignmentForOps($season->id, (int) $assignment->id);
            if ($source->booking_status !== 'confirmed') {
                throw AccommodationDomainException::illegalTransition();
            }

            $when = $this->parseOccurredAt($occurredAt);
            // CHECK requires effective_ends_at > starts_at on the closed source;
            // destination needs a positive half-open interval.
            if (! $when->gt($source->starts_at) || $when->gt($source->original_ends_at)) {
                throw AccommodationDomainException::relocateIntervalInvalid();
            }

            $destSeasonUnit = AccommodationSeasonUnit::query()
                ->where('season_id', $season->id)
                ->whereKey($destinationSeasonUnitId)
                ->first();
            if ($destSeasonUnit === null || ! $destSeasonUnit->is_active) {
                throw AccommodationDomainException::invalidReference();
            }

            $sourceSeasonUnit = AccommodationSeasonUnit::query()
                ->where('season_id', $season->id)
                ->whereKey($source->season_unit_id)
                ->firstOrFail();

            $this->locks->lockUnits([(int) $source->unit_id, (int) $destSeasonUnit->unit_id]);
            $this->locks->ensureAndLockPilgrimAnchors($season->id, [(int) $source->season_pilgrim_id]);
            $seasonUnits = $this->locks->lockSeasonUnits([(int) $source->season_unit_id, (int) $destSeasonUnit->id]);
            $positionIds = array_values(array_filter([
                $source->season_position_id !== null ? (int) $source->season_position_id : null,
                $destinationSeasonPositionId,
            ]));
            $this->locks->lockSeasonPositions($positionIds);
            $this->locks->lockAssignments([(int) $source->id]);

            $source = AccommodationAssignment::query()->whereKey($source->id)->lockForUpdate()->firstOrFail();
            if ($source->booking_status !== 'confirmed') {
                throw AccommodationDomainException::illegalTransition();
            }

            $sourceUnit = $seasonUnits->get($source->season_unit_id) ?? $sourceSeasonUnit;
            $destUnit = $seasonUnits->get($destSeasonUnit->id) ?? $destSeasonUnit;

            $this->access->lockAndAssert($actor, (int) $sourceUnit->facility_id);
            $this->access->lockAndAssert($actor, (int) $destUnit->facility_id);

            $pilgrim = SeasonPilgrim::query()
                ->where('season_id', $season->id)
                ->whereKey($source->season_pilgrim_id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertEligiblePilgrim($pilgrim);
            $familyGroupId = $this->resolveFamilyGroupId($destUnit, $pilgrim, $source->family_group_id);
            $this->assertAudience($destUnit, $pilgrim);

            $destStarts = $when->copy();
            $destEnds = $source->original_ends_at->copy();
            if (! $destEnds->gt($destStarts)) {
                throw AccommodationDomainException::relocateIntervalInvalid();
            }

            $this->assertModeAndPosition($destUnit, $destinationSeasonPositionId);

            $excludeIds = [(int) $source->id];
            $this->capacity->assertPilgrimNoOverlap((int) $pilgrim->id, $destStarts, $destEnds, $excludeIds);
            $this->capacity->assertCrossSeasonUnitNoOverlap(
                (int) $destUnit->unit_id,
                (int) $season->id,
                $destStarts,
                $destEnds,
                $excludeIds,
            );
            $this->capacity->assertSeasonUnitPeak($destUnit, $destStarts, $destEnds, $excludeIds, 1);

            if ($destinationSeasonPositionId !== null) {
                $position = AccommodationSeasonPosition::query()
                    ->whereKey($destinationSeasonPositionId)
                    ->lockForUpdate()
                    ->firstOrFail();
                if (! $position->is_active || (int) $position->season_unit_id !== (int) $destUnit->id) {
                    throw AccommodationDomainException::capacityConflict('الموضع غير مفعّل لهذا الموسم.');
                }
                $this->capacity->assertPositionExclusive((int) $position->id, $destStarts, $destEnds, $excludeIds);
            }

            $this->capacity->assertFamilyExclusivity($destUnit, $familyGroupId, $destStarts, $destEnds, $excludeIds);

            $sourceOps = $this->effectiveOps($source);
            $wasCheckedIn = $sourceOps === 'checked_in';

            $sourceFill = [
                'effective_ends_at' => $when->copy(),
            ];
            if ($wasCheckedIn) {
                $sourceFill['booking_status'] = 'completed';
                $sourceFill['operational_status'] = 'checked_out';
                $sourceFill['checkout_source'] = self::SOURCE_STAFF;
            } else {
                $sourceFill['booking_status'] = 'cancelled';
                if ($source->operational_status === null) {
                    $sourceFill['operational_status'] = 'assigned';
                }
            }
            $source->fill($sourceFill);
            AccommodationPersistence::save(fn () => $source->save());

            $destination = new AccommodationAssignment([
                'season_id' => $season->id,
                'season_pilgrim_id' => $source->season_pilgrim_id,
                'season_unit_id' => $destUnit->id,
                'unit_id' => $destUnit->unit_id,
                'season_position_id' => $destinationSeasonPositionId,
                'booking_status' => 'confirmed',
                'operational_status' => $checkInAfter ? 'checked_in' : 'assigned',
                'starts_at' => $destStarts,
                'original_ends_at' => $destEnds,
                'effective_ends_at' => $destEnds,
                'family_group_id' => $familyGroupId,
                'checkout_source' => null,
                'actual_departed_at' => null,
                'relocated_from_assignment_id' => $source->id,
                'created_by' => $actor->id,
            ]);

            if ((int) $destination->relocated_from_assignment_id === 0) {
                throw AccommodationDomainException::invalidReference();
            }

            AccommodationPersistence::save(fn () => $destination->save());

            if ((int) $destination->relocated_from_assignment_id === (int) $destination->id) {
                throw AccommodationDomainException::illegalTransition('مرجع النقل الذاتي غير مسموح.');
            }

            $meta = [
                'source_assignment_id' => (int) $source->id,
                'destination_assignment_id' => (int) $destination->id,
                'check_in_after' => $checkInAfter,
            ];

            $this->events->appendOnce(
                (int) $season->id,
                (int) $destination->id,
                AccommodationEvent::TYPE_RELOCATED,
                $when,
                $actor->id,
                self::SOURCE_STAFF,
                $this->idemKey('relocated', (int) $source->id, $when).':dest:'.$destination->id,
                $meta,
            );

            if ($checkInAfter) {
                $this->events->appendOnce(
                    (int) $season->id,
                    (int) $destination->id,
                    AccommodationEvent::TYPE_CHECKED_IN,
                    $when,
                    $actor->id,
                    self::SOURCE_STAFF,
                    $this->idemKey('checked_in', (int) $destination->id, $when),
                    ['via_relocate' => true, 'source_assignment_id' => (int) $source->id],
                );
            }

            $this->audit->record(
                AuthorizationSecurityEvent::ACCOMMODATION_ASSIGNMENT_RELOCATED,
                $actor->id,
                'accommodation_assignment',
                $destination->id,
                null,
                null,
                [
                    'after_values' => [
                        'source_assignment_id' => $source->id,
                        'destination_assignment_id' => $destination->id,
                        'source_booking_status' => $source->booking_status,
                        'destination_operational_status' => $destination->operational_status,
                        'occurred_at' => $when->utc()->toIso8601String(),
                    ],
                ],
                $request,
            );

            return [
                'source' => $source->fresh(),
                'destination' => $destination->fresh(),
            ];
        });
    }

    private function lockAssignmentForOps(int $seasonId, int $assignmentId): AccommodationAssignment
    {
        $row = AccommodationAssignment::query()
            ->where('season_id', $seasonId)
            ->whereKey($assignmentId)
            ->first();
        if ($row === null) {
            throw AccommodationDomainException::invalidReference();
        }

        $this->locks->lockUnits([(int) $row->unit_id]);
        $this->locks->ensureAndLockPilgrimAnchors($seasonId, [(int) $row->season_pilgrim_id]);
        $this->locks->lockSeasonUnits([(int) $row->season_unit_id]);
        if ($row->season_position_id !== null) {
            $this->locks->lockSeasonPositions([(int) $row->season_position_id]);
        }

        return $this->locks->lockAssignments([$assignmentId])->get($assignmentId)
            ?? throw AccommodationDomainException::invalidReference();
    }

    private function effectiveOps(AccommodationAssignment $row): string
    {
        return $row->operational_status ?? 'assigned';
    }

    private function assertVerb(User $actor, string $verb): void
    {
        if (! $actor->hasPermission($verb)) {
            throw AccommodationDomainException::forbidden();
        }
    }

    private function parseOccurredAt(?string $occurredAt): CarbonInterface
    {
        if ($occurredAt === null || $occurredAt === '') {
            return now('UTC');
        }

        return Carbon::parse($occurredAt)->utc();
    }

    private function idemKey(string $type, int $assignmentId, CarbonInterface $occurredAt): string
    {
        return $type.':'.$assignmentId.':'.$occurredAt->utc()->format('Y-m-d\TH:i:s\Z').':'.self::SOURCE_STAFF;
    }

    private function assertEligiblePilgrim(SeasonPilgrim $pilgrim): void
    {
        if (! in_array($pilgrim->status, [SeasonPilgrimStatus::Registered, SeasonPilgrimStatus::Active], true)) {
            throw AccommodationDomainException::ineligiblePilgrim();
        }
    }

    private function resolveFamilyGroupId(
        AccommodationSeasonUnit $seasonUnit,
        SeasonPilgrim $pilgrim,
        ?int $sourceSnapshot,
    ): ?int {
        if ($seasonUnit->audience !== 'family') {
            return null;
        }

        $familyId = $sourceSnapshot ?? $pilgrim->season_family_group_id;
        if ($familyId === null) {
            throw AccommodationDomainException::familyExclusivity(
                'تأكيد وحدة العائلات يتطلب مجموعة عائلة صالحة.',
            );
        }

        $exists = SeasonFamilyGroup::query()
            ->where('season_id', $pilgrim->season_id)
            ->whereKey($familyId)
            ->exists();

        if (! $exists) {
            throw AccommodationDomainException::familyExclusivity(
                'تأكيد وحدة العائلات يتطلب مجموعة عائلة صالحة.',
            );
        }

        return (int) $familyId;
    }

    private function assertAudience(AccommodationSeasonUnit $seasonUnit, SeasonPilgrim $pilgrim): void
    {
        $pilgrim->loadMissing('pilgrim');
        $gender = $pilgrim->pilgrim?->gender;

        if (! $gender instanceof PilgrimGender) {
            throw AccommodationDomainException::unknownGender();
        }

        $genderValue = $gender->value;
        if ($seasonUnit->audience === 'male' && $genderValue !== 'male') {
            throw AccommodationDomainException::audienceMismatch();
        }
        if ($seasonUnit->audience === 'female' && $genderValue !== 'female') {
            throw AccommodationDomainException::audienceMismatch();
        }

        $facility = AccommodationSeasonFacility::query()
            ->where('season_id', $seasonUnit->season_id)
            ->whereKey($seasonUnit->season_facility_id)
            ->first();

        if ($facility !== null && $facility->audience !== null && $facility->audience !== '') {
            if ($facility->audience === 'male' && $genderValue !== 'male') {
                throw AccommodationDomainException::audienceMismatch();
            }
            if ($facility->audience === 'female' && $genderValue !== 'female') {
                throw AccommodationDomainException::audienceMismatch();
            }
        }
    }

    private function assertModeAndPosition(AccommodationSeasonUnit $seasonUnit, ?int $seasonPositionId): void
    {
        if ($seasonUnit->allocation_mode === 'capacity') {
            if ($seasonPositionId !== null) {
                throw AccommodationDomainException::capacityConflict(
                    'نمط السعة لا يقبل موضعًا صريحًا عند النقل.',
                );
            }

            return;
        }

        if ($seasonUnit->allocation_mode === 'position' && $seasonPositionId === null) {
            throw AccommodationDomainException::capacityConflict(
                'نمط المواضع يتطلب موضعًا مفعّلًا عند النقل.',
            );
        }
    }
}
