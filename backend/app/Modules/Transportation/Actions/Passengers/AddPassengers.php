<?php

namespace App\Modules\Transportation\Actions\Passengers;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Models\User;
use App\Modules\SeasonPilgrims\Enums\SeasonPilgrimStatus;
use App\Modules\SeasonPilgrims\Models\SeasonFamilyGroup;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrim;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrimGroup;
use App\Modules\SeasonPilgrims\Models\SeasonProgram;
use App\Modules\SeasonPilgrims\Support\SeasonGuard;
use App\Modules\Transportation\Enums\AssignmentSource;
use App\Modules\Transportation\Enums\EntitlementStatus;
use App\Modules\Transportation\Enums\PassengerStatus;
use App\Modules\Transportation\Enums\RunStatus;
use App\Modules\Transportation\Enums\TimelineEventType;
use App\Modules\Transportation\Exceptions\TransportationDomainException;
use App\Modules\Transportation\Models\TransportRunPassenger;
use App\Modules\Transportation\Models\TransportSeasonVehicle;
use App\Modules\Transportation\Models\TransportVehicleRun;
use App\Modules\Transportation\Support\EntitlementEvaluator;
use App\Modules\Transportation\Support\RunLocks;
use App\Modules\Transportation\Support\TimelineRecorder;
use App\Modules\Transportation\Support\TransportAudit;
use App\Modules\Transportation\Support\TransportTransaction;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * ADR-0018 T5 / T9 / I6: materializes one passenger row per Active
 * SeasonPilgrim. Group / family sources are resolved now and snapshotted
 * (id + label, no FK). Capacity is count-based with no overbooking.
 *
 * All-or-nothing for every source: every resolved member is validated
 * (eligibility, duplicates, entitlement, capacity) before any row is
 * written; one failure rejects the whole command with the failing
 * SeasonPilgrim ids.
 *
 * Lock order: movement → run → season vehicle → season pilgrims (by id),
 * so capacity, duplicates and eligibility are checked against committed
 * state and serialize with concurrent adds and cancellations. Duplicate
 * and occupancy checks are locking reads so they see rows committed while
 * this transaction waited for its locks.
 */
final class AddPassengers
{
    public function __construct(
        private readonly EntitlementEvaluator $entitlements,
        private readonly TimelineRecorder $timeline,
        private readonly TransportAudit $audit,
    ) {}

    /**
     * @param  list<int>  $seasonPilgrimIds
     * @return array{run: TransportVehicleRun, added: int}
     */
    public function execute(
        User $actor,
        TransportVehicleRun $run,
        AssignmentSource $source,
        array $seasonPilgrimIds,
        ?int $sourceId,
        bool $override,
        ?string $overrideReason,
        Request $request,
    ): array {
        if ($override && ! $actor->hasPermission('transportation.override_entitlement')) {
            throw TransportationDomainException::entitlementOverrideForbidden();
        }

        return TransportTransaction::run(function () use ($actor, $run, $source, $seasonPilgrimIds, $sourceId, $override, $overrideReason, $request): array {
            [$movement, $locked] = RunLocks::lock($run->id);
            SeasonGuard::assertWritable($movement->season()->firstOrFail());

            if ($movement->status->isTerminal() || ! in_array($locked->status, [RunStatus::Planned, RunStatus::Boarding], true)) {
                throw TransportationDomainException::manifestLocked();
            }

            $seasonVehicleId = (int) $locked->currentVehicleAssignment()->value('transport_season_vehicle_id');
            /** @var TransportSeasonVehicle $seasonVehicle */
            $seasonVehicle = TransportSeasonVehicle::query()->whereKey($seasonVehicleId)->lockForUpdate()->firstOrFail();
            $seasonVehicle->load('vehicle');
            $capacity = $locked->capacity_override ?? $seasonVehicle->operational_capacity;

            [$candidateIds, $sourceLabel] = $this->resolveSource($locked->season_id, $source, $seasonPilgrimIds, $sourceId);

            $seasonPilgrims = SeasonPilgrim::query()
                ->where('season_id', $locked->season_id)
                ->whereKey($candidateIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $notEligible = array_values(array_filter(
                $candidateIds,
                fn (int $id): bool => ! $seasonPilgrims->has($id) || $seasonPilgrims->get($id)->status !== SeasonPilgrimStatus::Active,
            ));
            if ($notEligible !== []) {
                throw TransportationDomainException::passengerNotEligible($notEligible);
            }

            $duplicates = TransportRunPassenger::query()
                ->where('vehicle_run_id', $locked->id)
                ->whereIn('status', PassengerStatus::occupyingValues())
                ->whereIn('season_pilgrim_id', $candidateIds)
                ->orderBy('season_pilgrim_id')
                ->sharedLock()
                ->pluck('season_pilgrim_id')
                ->map(fn ($id): int => (int) $id)
                ->all();
            if ($duplicates !== []) {
                throw TransportationDomainException::passengerDuplicate($duplicates);
            }

            $statuses = [];
            foreach ($candidateIds as $id) {
                $statuses[$id] = $this->entitlements->evaluate($seasonPilgrims->get($id)->season_program_id, $seasonVehicle);
            }
            $mismatched = array_keys(array_filter($statuses, fn (EntitlementStatus $s): bool => $s === EntitlementStatus::Mismatch));

            if ($mismatched !== [] && ! $override) {
                throw TransportationDomainException::entitlementMismatch($mismatched);
            }

            $occupied = $locked->occupiedCount();
            if ($occupied + count($candidateIds) > $capacity) {
                throw TransportationDomainException::capacityExceeded($capacity, $occupied, count($candidateIds), $candidateIds);
            }

            $programCodes = SeasonProgram::query()
                ->whereKey($seasonPilgrims->pluck('season_program_id')->filter()->unique()->all())
                ->pluck('code', 'id');

            $vehicleClass = $seasonVehicle->vehicle?->vehicle_class?->value;
            $serviceLevel = $seasonVehicle->service_level?->value;
            $now = Carbon::now();

            foreach ($candidateIds as $id) {
                $programId = $seasonPilgrims->get($id)->season_program_id;
                $entitlement = $this->entitlements->entitlementFor($programId);
                $isOverride = $statuses[$id] === EntitlementStatus::Mismatch;

                try {
                    $passenger = TransportRunPassenger::query()->create([
                        'season_id' => $locked->season_id,
                        'vehicle_run_id' => $locked->id,
                        'season_pilgrim_id' => $id,
                        'status' => PassengerStatus::Assigned,
                        'assignment_source' => $source,
                        'source_group_id' => $source === AssignmentSource::Individual ? null : $sourceId,
                        'source_group_label' => $sourceLabel,
                        'program_id_at_assignment' => $programId,
                        'program_code_at_assignment' => $programId !== null ? $programCodes->get($programId) : null,
                        'entitlement_id_at_assignment' => $entitlement?->id,
                        'required_vehicle_class_at_assignment' => $entitlement?->vehicle_class?->value,
                        'required_service_level_at_assignment' => $entitlement?->service_level?->value,
                        'season_vehicle_id_at_assignment' => $seasonVehicle->id,
                        'vehicle_class_at_assignment' => $vehicleClass,
                        'service_level_at_assignment' => $serviceLevel,
                        'entitlement_status_at_assignment' => $statuses[$id],
                        'override_at_assignment' => $isOverride,
                        'override_reason_at_assignment' => $isOverride ? $overrideReason : null,
                        'entitlement_override' => $isOverride,
                        'entitlement_override_reason' => $isOverride ? $overrideReason : null,
                        'entitlement_overridden_by' => $isOverride ? $actor->id : null,
                        'entitlement_overridden_at' => $isOverride ? $now : null,
                        'assigned_at' => $now,
                        'assigned_by' => $actor->id,
                    ]);
                } catch (UniqueConstraintViolationException) {
                    throw TransportationDomainException::passengerDuplicate([$id]);
                }

                $this->timeline->record(
                    seasonId: $locked->season_id,
                    movementId: $movement->id,
                    type: TimelineEventType::PassengerAssigned,
                    occurredAt: $now,
                    actorId: $actor->id,
                    runId: $locked->id,
                    passengerId: $passenger->id,
                    reason: $isOverride ? $overrideReason : null,
                    context: array_filter([
                        'season_pilgrim_id' => $id,
                        'assignment_source' => $source->value,
                        'source_group_id' => $source === AssignmentSource::Individual ? null : $sourceId,
                        'season_program_id' => $programId,
                        'entitlement_id' => $entitlement?->id,
                        'transport_season_vehicle_id' => $seasonVehicle->id,
                        'entitlement_status' => $statuses[$id]->value,
                        'override' => $isOverride,
                    ], fn ($v) => $v !== null),
                );
            }

            $runLabel = $movement->movement_number.'/'.$locked->run_number;
            $this->audit->record(AuthorizationSecurityEvent::TRANSPORT_PASSENGERS_ADDED, $actor->id, 'transport_vehicle_run', $locked->id, $runLabel, null, [
                'metadata' => [
                    'season_id' => $locked->season_id,
                    'assignment_source' => $source->value,
                    'source_group_id' => $source === AssignmentSource::Individual ? null : $sourceId,
                    'season_pilgrim_ids' => $candidateIds,
                    'transport_season_vehicle_id' => $seasonVehicle->id,
                ],
            ], $request);

            if ($mismatched !== []) {
                $this->audit->record(AuthorizationSecurityEvent::TRANSPORT_PASSENGER_ENTITLEMENT_OVERRIDDEN, $actor->id, 'transport_vehicle_run', $locked->id, $runLabel, null, [
                    'reason' => $overrideReason,
                    'metadata' => ['season_id' => $locked->season_id, 'season_pilgrim_ids' => $mismatched, 'at_assignment' => true],
                ], $request);
            }

            return ['run' => $locked, 'added' => count($candidateIds)];
        });
    }

    /**
     * @param  list<int>  $seasonPilgrimIds
     * @return array{0: list<int>, 1: string|null}
     */
    private function resolveSource(int $seasonId, AssignmentSource $source, array $seasonPilgrimIds, ?int $sourceId): array
    {
        if ($source === AssignmentSource::Individual) {
            $ids = array_values(array_unique(array_map('intval', $seasonPilgrimIds)));
            sort($ids);

            return [$ids, null];
        }

        $group = $source === AssignmentSource::PilgrimGroup
            ? SeasonPilgrimGroup::query()->where('season_id', $seasonId)->whereKey((int) $sourceId)->first()
            : SeasonFamilyGroup::query()->where('season_id', $seasonId)->whereKey((int) $sourceId)->first();

        if ($group === null) {
            throw TransportationDomainException::sourceInvalid();
        }

        $column = $source === AssignmentSource::PilgrimGroup ? 'season_pilgrim_group_id' : 'season_family_group_id';

        $ids = SeasonPilgrim::query()
            ->where('season_id', $seasonId)
            ->where($column, $group->id)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($ids === []) {
            throw TransportationDomainException::passengersNoneEligible();
        }

        return [$ids, (string) $group->name];
    }
}
