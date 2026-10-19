<?php

namespace App\Modules\Accommodation\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Models\User;
use App\Modules\Accommodation\Exceptions\AccommodationDomainException;
use App\Modules\Accommodation\Models\AccommodationAssignment;
use App\Modules\Accommodation\Models\AccommodationSeasonFacility;
use App\Modules\Accommodation\Models\AccommodationSeasonPosition;
use App\Modules\Accommodation\Models\AccommodationSeasonUnit;
use App\Modules\Accommodation\Support\AccommodationAccess;
use App\Modules\Accommodation\Support\AccommodationAudit;
use App\Modules\Accommodation\Support\AccommodationCapacityGuard;
use App\Modules\Accommodation\Support\AccommodationLockOrder;
use App\Modules\Accommodation\Support\AccommodationPersistence;
use App\Modules\Accommodation\Support\AccommodationTransaction;
use App\Modules\SeasonPilgrims\Context\SeasonContext;
use App\Modules\SeasonPilgrims\Enums\PilgrimGender;
use App\Modules\SeasonPilgrims\Enums\SeasonPilgrimStatus;
use App\Modules\SeasonPilgrims\Models\SeasonFamilyGroup;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrim;
use App\Modules\SeasonPilgrims\Support\SeasonGuard;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class AccommodationConfirmationActions
{
    public function __construct(
        private readonly AccommodationAudit $audit,
        private readonly AccommodationAccess $access,
        private readonly AccommodationLockOrder $locks,
        private readonly AccommodationCapacityGuard $capacity,
        private readonly SeasonContext $seasons,
    ) {}

    public function confirmOne(User $actor, AccommodationAssignment $assignment, Request $request): AccommodationAssignment
    {
        $confirmed = $this->confirmMany($actor, [$assignment->id], $request);

        return $confirmed->firstOrFail();
    }

    /**
     * Atomic family confirmation for a stated Foundation family group + draft ids.
     *
     * @param  list<int>  $assignmentIds
     * @return Collection<int, AccommodationAssignment>
     */
    public function confirmFamily(User $actor, int $seasonFamilyGroupId, array $assignmentIds, Request $request): Collection
    {
        $ids = array_values(array_unique(array_map('intval', $assignmentIds)));
        if ($ids === [] || $seasonFamilyGroupId <= 0) {
            throw AccommodationDomainException::invalidReference();
        }

        return $this->confirmMany($actor, $ids, $request, $seasonFamilyGroupId);
    }

    /**
     * Atomic multi-draft confirmation. All succeed or none.
     *
     * @param  list<int>  $assignmentIds
     * @return Collection<int, AccommodationAssignment>
     */
    public function confirmMany(
        User $actor,
        array $assignmentIds,
        Request $request,
        ?int $requiredSeasonFamilyGroupId = null,
    ): Collection {
        $ids = array_values(array_unique(array_map('intval', $assignmentIds)));
        if ($ids === []) {
            throw AccommodationDomainException::invalidReference();
        }

        return AccommodationTransaction::run(function () use ($actor, $ids, $request, $requiredSeasonFamilyGroupId): Collection {
            $season = $this->seasons->require();
            SeasonGuard::assertActive($season);

            if (! $actor->hasPermission('accommodation.confirm')) {
                throw AccommodationDomainException::forbidden();
            }

            if ($requiredSeasonFamilyGroupId !== null) {
                $familyExists = SeasonFamilyGroup::query()
                    ->where('season_id', $season->id)
                    ->whereKey($requiredSeasonFamilyGroupId)
                    ->exists();
                if (! $familyExists) {
                    throw AccommodationDomainException::familyExclusivity(
                        'تأكيد وحدة العائلات يتطلب مجموعة عائلة صالحة.',
                    );
                }
            }

            /** @var Collection<int, AccommodationAssignment> $drafts */
            $drafts = AccommodationAssignment::query()
                ->where('season_id', $season->id)
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->get()
                ->keyBy('id');

            if ($drafts->count() !== count($ids)) {
                throw AccommodationDomainException::invalidReference();
            }

            foreach ($drafts as $draft) {
                if ($draft->booking_status === 'confirmed') {
                    continue;
                }
                if ($draft->booking_status !== 'draft') {
                    throw AccommodationDomainException::notDraft();
                }
            }

            $toConfirm = $drafts->filter(fn (AccommodationAssignment $row): bool => $row->booking_status === 'draft');
            if ($toConfirm->isEmpty()) {
                return $drafts->values();
            }

            $seasonUnitIds = $toConfirm->pluck('season_unit_id')->map(fn ($id) => (int) $id)->all();
            $unitIds = $toConfirm->pluck('unit_id')->map(fn ($id) => (int) $id)->all();
            $positionIds = $toConfirm->pluck('season_position_id')->filter()->map(fn ($id) => (int) $id)->all();
            $pilgrimIds = $toConfirm->pluck('season_pilgrim_id')->map(fn ($id) => (int) $id)->all();

            $this->locks->lockUnits($unitIds);
            $this->locks->ensureAndLockPilgrimAnchors($season->id, $pilgrimIds);
            $seasonUnits = $this->locks->lockSeasonUnits($seasonUnitIds);
            $this->locks->lockSeasonPositions($positionIds);
            $lockedAssignments = $this->locks->lockAssignments($toConfirm->pluck('id')->all());

            $excludeIds = $toConfirm->pluck('id')->map(fn ($id) => (int) $id)->all();
            /** @var list<array{season_position_id: int, starts_at: CarbonInterface, ends_at: CarbonInterface}> $pendingPositions */
            $pendingPositions = [];
            /** @var list<array{season_pilgrim_id: int, starts_at: CarbonInterface, ends_at: CarbonInterface}> $pendingPilgrims */
            $pendingPilgrims = [];
            $pendingFamily = [];

            foreach ($toConfirm->sortBy('id') as $draft) {
                $locked = $lockedAssignments->get($draft->id);
                if ($locked === null || $locked->booking_status !== 'draft') {
                    throw AccommodationDomainException::notDraft();
                }

                $seasonUnit = $seasonUnits->get($locked->season_unit_id);
                if ($seasonUnit === null || ! $seasonUnit->is_active) {
                    throw AccommodationDomainException::invalidReference();
                }

                $this->access->lockAndAssert($actor, (int) $seasonUnit->facility_id);

                $pilgrim = SeasonPilgrim::query()
                    ->where('season_id', $season->id)
                    ->whereKey($locked->season_pilgrim_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($requiredSeasonFamilyGroupId !== null
                    && (int) $pilgrim->season_family_group_id !== $requiredSeasonFamilyGroupId) {
                    throw AccommodationDomainException::familyExclusivity(
                        'مسودة التأكيد لا تنتمي لمجموعة العائلة المحددة.',
                    );
                }

                $this->assertEligiblePilgrim($pilgrim);
                $familyGroupId = $this->resolveFamilyGroupId($seasonUnit, $pilgrim);
                $this->assertAudience($seasonUnit, $pilgrim);

                $startsAt = $locked->starts_at;
                $endsAt = $locked->effective_ends_at;
                if (! $endsAt->gt($startsAt)) {
                    throw AccommodationDomainException::intervalInvalid();
                }

                $this->assertModeAndPosition($seasonUnit, $locked);

                foreach ($pendingPilgrims as $pending) {
                    if ((int) $pending['season_pilgrim_id'] !== (int) $pilgrim->id) {
                        continue;
                    }
                    if ($this->capacity->intervalsOverlap($pending['starts_at'], $pending['ends_at'], $startsAt, $endsAt)) {
                        throw AccommodationDomainException::pilgrimOverlap();
                    }
                }
                $pendingPilgrims[] = [
                    'season_pilgrim_id' => (int) $pilgrim->id,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                ];

                if ($locked->season_position_id !== null) {
                    foreach ($pendingPositions as $pending) {
                        if ((int) $pending['season_position_id'] !== (int) $locked->season_position_id) {
                            continue;
                        }
                        if ($this->capacity->intervalsOverlap($pending['starts_at'], $pending['ends_at'], $startsAt, $endsAt)) {
                            throw AccommodationDomainException::capacityConflict('الموضع محجوز بتسكين مؤكد متداخل.');
                        }
                    }
                    $pendingPositions[] = [
                        'season_position_id' => (int) $locked->season_position_id,
                        'starts_at' => $startsAt,
                        'ends_at' => $endsAt,
                    ];
                }

                $unitKey = (int) $seasonUnit->id;
                if ($seasonUnit->audience === 'family') {
                    $pendingFamily[$unitKey][] = [
                        'family_group_id' => $familyGroupId,
                        'starts_at' => $startsAt,
                        'ends_at' => $endsAt,
                    ];
                }

                $batchMates = $toConfirm->filter(function (AccommodationAssignment $other) use ($locked, $startsAt, $endsAt): bool {
                    if ((int) $other->id === (int) $locked->id) {
                        return false;
                    }
                    if ((int) $other->season_unit_id !== (int) $locked->season_unit_id) {
                        return false;
                    }

                    return $this->capacity->intervalsOverlap(
                        $other->starts_at,
                        $other->effective_ends_at,
                        $startsAt,
                        $endsAt,
                    );
                })->count();

                $this->capacity->assertPilgrimNoOverlap((int) $pilgrim->id, $startsAt, $endsAt, $excludeIds);
                $this->capacity->assertCrossSeasonUnitNoOverlap(
                    (int) $locked->unit_id,
                    (int) $season->id,
                    $startsAt,
                    $endsAt,
                    $excludeIds,
                );
                $this->capacity->assertSeasonUnitPeak(
                    $seasonUnit,
                    $startsAt,
                    $endsAt,
                    $excludeIds,
                    1 + $batchMates,
                );

                if ($locked->season_position_id !== null) {
                    $position = AccommodationSeasonPosition::query()
                        ->whereKey($locked->season_position_id)
                        ->lockForUpdate()
                        ->firstOrFail();
                    if (! $position->is_active || (int) $position->season_unit_id !== (int) $seasonUnit->id) {
                        throw AccommodationDomainException::capacityConflict('الموضع غير مفعّل لهذا الموسم.');
                    }
                    $this->capacity->assertPositionExclusive((int) $position->id, $startsAt, $endsAt, $excludeIds);
                }

                $this->capacity->assertFamilyExclusivity($seasonUnit, $familyGroupId, $startsAt, $endsAt, $excludeIds);
                $this->assertPendingFamilyBatch($pendingFamily[$unitKey] ?? []);

                $locked->fill([
                    'booking_status' => 'confirmed',
                    'operational_status' => null,
                    'checkout_source' => null,
                    'family_group_id' => $familyGroupId,
                    'effective_ends_at' => $locked->original_ends_at,
                ]);
                AccommodationPersistence::save(fn () => $locked->save());

                $this->audit->record(
                    AuthorizationSecurityEvent::ACCOMMODATION_ASSIGNMENT_CONFIRMED,
                    $actor->id,
                    'accommodation_assignment',
                    $locked->id,
                    null,
                    null,
                    [
                        'after_values' => [
                            'booking_status' => 'confirmed',
                            'season_unit_id' => $locked->season_unit_id,
                            'season_pilgrim_id' => $locked->season_pilgrim_id,
                            'family_group_id' => $familyGroupId,
                            'capacity_reserved' => true,
                            'starts_at' => $startsAt->utc()->toIso8601String(),
                            'effective_ends_at' => $endsAt->utc()->toIso8601String(),
                        ],
                    ],
                    $request,
                );
            }

            return AccommodationAssignment::query()
                ->where('season_id', $season->id)
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->get();
        });
    }

    private function assertEligiblePilgrim(SeasonPilgrim $pilgrim): void
    {
        if (! in_array($pilgrim->status, [SeasonPilgrimStatus::Registered, SeasonPilgrimStatus::Active], true)) {
            throw AccommodationDomainException::ineligiblePilgrim();
        }
    }

    private function resolveFamilyGroupId(AccommodationSeasonUnit $seasonUnit, SeasonPilgrim $pilgrim): ?int
    {
        if ($seasonUnit->audience !== 'family') {
            return null;
        }

        $familyId = $pilgrim->season_family_group_id;
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
        $unitAudience = $seasonUnit->audience;

        if ($unitAudience === 'male' && $genderValue !== 'male') {
            throw AccommodationDomainException::audienceMismatch();
        }
        if ($unitAudience === 'female' && $genderValue !== 'female') {
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

    private function assertModeAndPosition(AccommodationSeasonUnit $seasonUnit, AccommodationAssignment $locked): void
    {
        if ($seasonUnit->allocation_mode === 'capacity') {
            if ($locked->season_position_id !== null) {
                throw AccommodationDomainException::capacityConflict(
                    'نمط السعة لا يقبل موضعًا صريحًا عند التأكيد.',
                );
            }

            return;
        }

        if ($seasonUnit->allocation_mode === 'position' && $locked->season_position_id === null) {
            throw AccommodationDomainException::capacityConflict(
                'نمط المواضع يتطلب موضعًا مفعّلًا عند التأكيد.',
            );
        }
    }

    /**
     * @param  list<array{family_group_id: int|null, starts_at: CarbonInterface, ends_at: CarbonInterface}>  $batch
     */
    private function assertPendingFamilyBatch(array $batch): void
    {
        $n = count($batch);
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $a = $batch[$i];
                $b = $batch[$j];
                if (! $this->capacity->intervalsOverlap($a['starts_at'], $a['ends_at'], $b['starts_at'], $b['ends_at'])) {
                    continue;
                }
                if ($a['family_group_id'] !== $b['family_group_id']) {
                    throw AccommodationDomainException::familyExclusivity(
                        'لا يمكن تداخل مجموعات عائلات مختلفة في نفس وحدة العائلات.',
                    );
                }
            }
        }
    }
}
