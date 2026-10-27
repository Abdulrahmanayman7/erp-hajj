<?php

namespace App\Modules\Accommodation\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Models\User;
use App\Modules\Accommodation\Exceptions\AccommodationDomainException;
use App\Modules\Accommodation\Models\AccommodationAssignment;
use App\Modules\Accommodation\Models\AccommodationSeasonFacility;
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
            $positions = $this->locks->lockSeasonPositions($positionIds);
            $lockedAssignments = $this->locks->lockAssignments($toConfirm->pluck('id')->all());

            // Transaction-local references only: no authorization or occupancy cache
            // survives commit/retry/request. Participation locks remain deterministic.
            $pilgrims = SeasonPilgrim::query()->where('season_id', $season->id)
                ->whereIn('id', $lockedAssignments->pluck('season_pilgrim_id')->unique()->all())
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $pilgrims->load('pilgrim');
            $families = SeasonFamilyGroup::query()->where('season_id', $season->id)
                ->whereIn('id', $pilgrims->pluck('season_family_group_id')->filter()->unique()->all())
                ->pluck('id')->mapWithKeys(fn ($id) => [(int) $id => true])->all();
            $facilities = AccommodationSeasonFacility::query()->where('season_id', $season->id)
                ->whereIn('id', $seasonUnits->pluck('season_facility_id')->unique()->all())->get()->keyBy('id');
            $batchesByUnit = $lockedAssignments->groupBy('season_unit_id');
            $checkedFacilities = [];
            $checkedUnits = [];

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

                if (! isset($checkedFacilities[$seasonUnit->facility_id])) {
                    $this->access->lockAndAssert($actor, (int) $seasonUnit->facility_id);
                    $checkedFacilities[$seasonUnit->facility_id] = true;
                }

                $pilgrim = $pilgrims->get($locked->season_pilgrim_id)
                    ?? throw AccommodationDomainException::invalidReference();

                if ($requiredSeasonFamilyGroupId !== null
                    && (int) $pilgrim->season_family_group_id !== $requiredSeasonFamilyGroupId) {
                    throw AccommodationDomainException::familyExclusivity(
                        'مسودة التأكيد لا تنتمي لمجموعة العائلة المحددة.',
                    );
                }

                $this->assertEligiblePilgrim($pilgrim);
                $familyGroupId = $this->resolveFamilyGroupId($seasonUnit, $pilgrim, $families);
                $this->assertAudience($seasonUnit, $pilgrim, $facilities->get($seasonUnit->season_facility_id));

                $startsAt = $locked->starts_at;
                $endsAt = $locked->effective_ends_at;
                if (! $endsAt->gt($startsAt)) {
                    throw AccommodationDomainException::intervalInvalid();
                }

                $this->assertModeAndPosition($seasonUnit, $locked);

                foreach ($pendingPilgrims[$pilgrim->id] ?? [] as $pending) {
                    if ((int) $pending['season_pilgrim_id'] !== (int) $pilgrim->id) {
                        continue;
                    }
                    if ($this->capacity->intervalsOverlap($pending['starts_at'], $pending['ends_at'], $startsAt, $endsAt)) {
                        throw AccommodationDomainException::pilgrimOverlap();
                    }
                }
                $pendingPilgrims[$pilgrim->id][] = [
                    'season_pilgrim_id' => (int) $pilgrim->id,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                ];

                if ($locked->season_position_id !== null) {
                    foreach ($pendingPositions[$locked->season_position_id] ?? [] as $pending) {
                        if ((int) $pending['season_position_id'] !== (int) $locked->season_position_id) {
                            continue;
                        }
                        if ($this->capacity->intervalsOverlap($pending['starts_at'], $pending['ends_at'], $startsAt, $endsAt)) {
                            throw AccommodationDomainException::capacityConflict('الموضع محجوز بتسكين مؤكد متداخل.');
                        }
                    }
                    $pendingPositions[$locked->season_position_id][] = [
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

                $this->capacity->assertPilgrimNoOverlap((int) $pilgrim->id, $startsAt, $endsAt, $excludeIds);
                $this->capacity->assertCrossSeasonUnitNoOverlap(
                    (int) $locked->unit_id,
                    (int) $season->id,
                    $startsAt,
                    $endsAt,
                    $excludeIds,
                );
                if (! isset($checkedUnits[$seasonUnit->id])) {
                    $this->capacity->assertSeasonUnitBatchPeak($seasonUnit, $batchesByUnit->get($seasonUnit->id), $excludeIds);
                    $checkedUnits[$seasonUnit->id] = true;
                }

                if ($locked->season_position_id !== null) {
                    $position = $positions->get($locked->season_position_id)
                        ?? throw AccommodationDomainException::invalidReference();
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

    private function resolveFamilyGroupId(AccommodationSeasonUnit $seasonUnit, SeasonPilgrim $pilgrim, array $families): ?int
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

        $exists = isset($families[(int) $familyId]);

        if (! $exists) {
            throw AccommodationDomainException::familyExclusivity(
                'تأكيد وحدة العائلات يتطلب مجموعة عائلة صالحة.',
            );
        }

        return (int) $familyId;
    }

    private function assertAudience(AccommodationSeasonUnit $seasonUnit, SeasonPilgrim $pilgrim, ?AccommodationSeasonFacility $facility): void
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
        // Earlier prefix pairs were already checked on previous iterations.
        $last = array_pop($batch);
        if ($last === null) {
            return;
        }
        foreach ($batch as $previous) {
            if ($previous['family_group_id'] !== $last['family_group_id']
                && $this->capacity->intervalsOverlap($previous['starts_at'], $previous['ends_at'], $last['starts_at'], $last['ends_at'])) {
                throw AccommodationDomainException::familyExclusivity(
                    'لا يمكن تداخل مجموعات عائلات مختلفة في نفس وحدة العائلات.',
                );
            }
        }
    }
}
