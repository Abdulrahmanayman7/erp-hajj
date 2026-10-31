<?php

namespace App\Modules\Transportation\Actions\Breakdowns;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Models\User;
use App\Modules\SeasonPilgrims\Enums\SeasonPilgrimStatus;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrim;
use App\Modules\SeasonPilgrims\Models\SeasonProgram;
use App\Modules\SeasonPilgrims\Support\SeasonGuard;
use App\Modules\Transportation\Actions\Runs\RunLoader;
use App\Modules\Transportation\Actions\Runs\RunSubstitution;
use App\Modules\Transportation\Enums\AssignmentSource;
use App\Modules\Transportation\Enums\BreakdownStatus;
use App\Modules\Transportation\Enums\EntitlementStatus;
use App\Modules\Transportation\Enums\PassengerStatus;
use App\Modules\Transportation\Enums\ReplacementStatus;
use App\Modules\Transportation\Enums\RunStatus;
use App\Modules\Transportation\Enums\TimelineEventType;
use App\Modules\Transportation\Exceptions\TransportationDomainException;
use App\Modules\Transportation\Models\TransportReplacementPassengerMove;
use App\Modules\Transportation\Models\TransportRunPassenger;
use App\Modules\Transportation\Models\TransportRunVehicleAssignment;
use App\Modules\Transportation\Models\TransportSeasonVehicle;
use App\Modules\Transportation\Models\TransportVehicleBreakdown;
use App\Modules\Transportation\Models\TransportVehicleReplacement;
use App\Modules\Transportation\Models\TransportVehicleRun;
use App\Modules\Transportation\Support\EntitlementEvaluator;
use App\Modules\Transportation\Support\PassengerCancellation;
use App\Modules\Transportation\Support\ResourceOverlapGuard;
use App\Modules\Transportation\Support\RunLocks;
use App\Modules\Transportation\Support\SeasonVehicleResolver;
use App\Modules\Transportation\Support\TimelineRecorder;
use App\Modules\Transportation\Support\TransportAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Atomic breakdown replacement: optional explicit passenger moves, then T7
 * vehicle history append. Never drops passengers silently.
 *
 * Lock order: breakdown → movement → source run → target runs (by id) →
 * season vehicles (by id) → season pilgrims (by id) → passengers.
 */
final class ExecuteReplacement
{
    public function __construct(
        private readonly ResourceOverlapGuard $overlap,
        private readonly EntitlementEvaluator $entitlements,
        private readonly PassengerCancellation $cancellation,
        private readonly TimelineRecorder $timeline,
        private readonly TransportAudit $audit,
    ) {}

    /**
     * @param  array{
     *   replacement_season_vehicle_id: int,
     *   reason?: string|null,
     *   idempotency_key?: string|null,
     *   reassignments?: list<array{run_passenger_id: int, target_run_id: int}>
     * }  $data
     * @return array{breakdown: TransportVehicleBreakdown, replacement: TransportVehicleReplacement, run: TransportVehicleRun}
     */
    public function execute(User $actor, TransportVehicleBreakdown $breakdown, array $data, Request $request): array
    {
        $idempotency = isset($data['idempotency_key']) && is_string($data['idempotency_key']) && trim($data['idempotency_key']) !== ''
            ? trim($data['idempotency_key'])
            : null;

        return DB::transaction(function () use ($actor, $breakdown, $data, $request, $idempotency): array {
            if ($idempotency !== null) {
                $existing = TransportVehicleReplacement::query()
                    ->where('idempotency_key', $idempotency)
                    ->first();
                if ($existing !== null) {
                    $bd = TransportVehicleBreakdown::query()->whereKey($existing->breakdown_id)->firstOrFail();

                    return [
                        'breakdown' => $bd,
                        'replacement' => $existing,
                        'run' => RunLoader::load(TransportVehicleRun::query()->whereKey($existing->vehicle_run_id)->firstOrFail()),
                    ];
                }
            }

            /** @var TransportVehicleBreakdown $bd */
            $bd = TransportVehicleBreakdown::query()->whereKey($breakdown->id)->lockForUpdate()->firstOrFail();
            if ($bd->status !== BreakdownStatus::Open) {
                throw TransportationDomainException::breakdownNotOpen();
            }
            if ($bd->replacement_id !== null) {
                $existing = TransportVehicleReplacement::query()->whereKey($bd->replacement_id)->firstOrFail();

                return [
                    'breakdown' => $bd,
                    'replacement' => $existing,
                    'run' => RunLoader::load(TransportVehicleRun::query()->whereKey($existing->vehicle_run_id)->firstOrFail()),
                ];
            }

            [$movement, $sourceRun] = RunLocks::lock($bd->vehicle_run_id);
            SeasonGuard::assertWritable($movement->season()->firstOrFail());
            if ($sourceRun->status->isTerminal()) {
                throw TransportationDomainException::breakdownRunTerminal();
            }

            $reason = RunSubstitution::assertAllowed($sourceRun, $data['reason'] ?? $bd->reason);

            /** @var TransportRunVehicleAssignment|null $current */
            $current = $sourceRun->currentVehicleAssignment()->lockForUpdate()->first();
            if ($current === null || (int) $current->transport_season_vehicle_id !== (int) $bd->transport_season_vehicle_id) {
                throw TransportationDomainException::breakdownVehicleMismatch();
            }

            $toId = (int) $data['replacement_season_vehicle_id'];
            if ($toId === (int) $bd->transport_season_vehicle_id) {
                throw TransportationDomainException::replacementSameVehicle();
            }

            /** @var list<array{run_passenger_id: int, target_run_id: int}> $plan */
            $plan = array_values($data['reassignments'] ?? []);
            $targetRunIds = array_values(array_unique(array_map(fn (array $row): int => (int) $row['target_run_id'], $plan)));
            sort($targetRunIds);

            $lockedTargets = [];
            foreach ($targetRunIds as $targetRunId) {
                if ($targetRunId === $sourceRun->id) {
                    throw TransportationDomainException::replacementPlanInvalid();
                }
                [$targetMovement, $targetRun] = RunLocks::lock($targetRunId);
                if ($targetRun->season_id !== $sourceRun->season_id) {
                    throw TransportationDomainException::replacementTargetRunInvalid();
                }
                if (! in_array($targetRun->status, [RunStatus::Planned, RunStatus::Boarding], true)) {
                    throw TransportationDomainException::replacementTargetRunInvalid();
                }
                if ($targetMovement->status->isTerminal()) {
                    throw TransportationDomainException::replacementTargetRunInvalid();
                }
                $lockedTargets[$targetRunId] = $targetRun;
            }

            $vehicleIds = [$current->transport_season_vehicle_id, $toId];
            foreach ($lockedTargets as $targetRun) {
                $tv = $targetRun->currentVehicleAssignment()->lockForUpdate()->value('transport_season_vehicle_id');
                if ($tv !== null) {
                    $vehicleIds[] = (int) $tv;
                }
            }
            $vehicleIds = array_values(array_unique($vehicleIds));
            sort($vehicleIds);
            foreach ($vehicleIds as $vid) {
                SeasonVehicleResolver::activeInSeason($sourceRun->season_id, $vid);
            }

            $replacementSv = SeasonVehicleResolver::activeInSeason($sourceRun->season_id, $toId);
            $this->overlap->assertVehicleFree($replacementSv->id, $movement, $sourceRun->id);

            $occupying = TransportRunPassenger::query()
                ->where('vehicle_run_id', $sourceRun->id)
                ->whereIn('status', PassengerStatus::occupyingValues())
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id', 'status']);
            $occupied = $occupying->count();
            $boarded = $occupying
                ->whereIn('status', [PassengerStatus::Boarded, PassengerStatus::Arrived])
                ->count();

            $effectiveCapacity = (int) $replacementSv->operational_capacity;
            if ($sourceRun->capacity_override !== null) {
                if ($sourceRun->capacity_override > $replacementSv->operational_capacity) {
                    throw TransportationDomainException::runCapacityOverrideInvalid();
                }
                $effectiveCapacity = (int) $sourceRun->capacity_override;
            }

            if ($boarded > $effectiveCapacity) {
                throw TransportationDomainException::replacementBoardedExceedCapacity();
            }

            $gap = max(0, $occupied - $effectiveCapacity);
            if ($gap > 0 && count($plan) < $gap) {
                throw TransportationDomainException::replacementCapacityPlanRequired($gap);
            }

            $moves = $this->applyReassignments($actor, $sourceRun, $lockedTargets, $plan, $request);
            $remainingOccupied = TransportRunPassenger::query()
                ->where('vehicle_run_id', $sourceRun->id)
                ->whereIn('status', PassengerStatus::occupyingValues())
                ->orderBy('id')
                ->lockForUpdate()
                ->count();
            if ($remainingOccupied > $effectiveCapacity) {
                throw TransportationDomainException::capacityBelowAssigned();
            }

            $now = Carbon::now();
            $current->update(['ended_at' => $now, 'ended_by' => $actor->id]);
            $assignment = TransportRunVehicleAssignment::query()->create([
                'season_id' => $sourceRun->season_id,
                'vehicle_run_id' => $sourceRun->id,
                'transport_season_vehicle_id' => $replacementSv->id,
                'assigned_at' => $now,
                'assigned_by' => $actor->id,
                'reason' => $reason,
            ]);

            $replacement = TransportVehicleReplacement::query()->create([
                'season_id' => $sourceRun->season_id,
                'breakdown_id' => $bd->id,
                'vehicle_run_id' => $sourceRun->id,
                'from_season_vehicle_id' => $bd->transport_season_vehicle_id,
                'to_season_vehicle_id' => $replacementSv->id,
                'status' => ReplacementStatus::Completed,
                'reason' => $reason,
                'passengers_retained' => $remainingOccupied,
                'passengers_reassigned' => count($moves),
                'idempotency_key' => $idempotency,
                'executed_at' => $now,
                'executed_by' => $actor->id,
            ]);

            foreach ($moves as $move) {
                TransportReplacementPassengerMove::query()->create([
                    'season_id' => $sourceRun->season_id,
                    'replacement_id' => $replacement->id,
                    ...$move,
                ]);
            }

            $bd->update([
                'status' => BreakdownStatus::Replaced,
                'replacement_id' => $replacement->id,
                'closed_at' => $now,
                'closed_by' => $actor->id,
                'closure_reason' => $reason,
            ]);

            $this->timeline->record(
                seasonId: $sourceRun->season_id,
                movementId: $movement->id,
                type: TimelineEventType::VehicleAssigned,
                occurredAt: $now,
                actorId: $actor->id,
                runId: $sourceRun->id,
                reason: $reason,
                context: [
                    'previous_season_vehicle_id' => $current->transport_season_vehicle_id,
                    'transport_season_vehicle_id' => $replacementSv->id,
                    'assignment_id' => $assignment->id,
                    'breakdown_id' => $bd->id,
                    'replacement_id' => $replacement->id,
                ],
            );
            $this->timeline->record(
                seasonId: $sourceRun->season_id,
                movementId: $movement->id,
                type: TimelineEventType::VehicleReplaced,
                occurredAt: $now,
                actorId: $actor->id,
                runId: $sourceRun->id,
                reason: $reason,
                context: [
                    'breakdown_id' => $bd->id,
                    'replacement_id' => $replacement->id,
                    'passengers_reassigned' => count($moves),
                ],
            );

            $label = $movement->movement_number.'/'.$sourceRun->run_number;
            $this->audit->record(AuthorizationSecurityEvent::TRANSPORT_RUN_VEHICLE_ASSIGNED, $actor->id, 'transport_vehicle_run', $sourceRun->id, $label, null, [
                'before_values' => ['transport_season_vehicle_id' => $current->transport_season_vehicle_id],
                'after_values' => ['transport_season_vehicle_id' => $replacementSv->id],
                'reason' => $reason,
                'metadata' => ['season_id' => $sourceRun->season_id, 'breakdown_id' => $bd->id, 'via' => 'breakdown_replacement'],
            ], $request);
            $this->audit->record(AuthorizationSecurityEvent::TRANSPORT_VEHICLE_REPLACEMENT_EXECUTED, $actor->id, 'transport_vehicle_replacement', $replacement->id, $label, null, [
                'metadata' => [
                    'season_id' => $sourceRun->season_id,
                    'breakdown_id' => $bd->id,
                    'from_season_vehicle_id' => $replacement->from_season_vehicle_id,
                    'to_season_vehicle_id' => $replacement->to_season_vehicle_id,
                    'passengers_reassigned' => count($moves),
                    'idempotency_key' => $idempotency,
                ],
                'reason' => $reason,
            ], $request);

            return [
                'breakdown' => $bd->fresh(),
                'replacement' => $replacement->fresh(['passengerMoves']),
                'run' => RunLoader::load($sourceRun),
            ];
        }, 5);
    }

    /**
     * @param  array<int, TransportVehicleRun>  $lockedTargets
     * @param  list<array{run_passenger_id: int, target_run_id: int}>  $plan
     * @return list<array{from_run_id: int, to_run_id: int, season_pilgrim_id: int, source_passenger_id: int, target_passenger_id: int}>
     */
    private function applyReassignments(
        User $actor,
        TransportVehicleRun $sourceRun,
        array $lockedTargets,
        array $plan,
        Request $request,
    ): array {
        if ($plan === []) {
            return [];
        }

        $passengerIds = array_map(fn (array $row): int => (int) $row['run_passenger_id'], $plan);
        sort($passengerIds);
        $passengers = TransportRunPassenger::query()
            ->where('vehicle_run_id', $sourceRun->id)
            ->whereKey($passengerIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        if ($passengers->count() !== count(array_unique($passengerIds))) {
            throw TransportationDomainException::replacementPlanInvalid($passengerIds);
        }

        foreach ($passengers as $passenger) {
            if ($passenger->status !== PassengerStatus::Assigned) {
                throw TransportationDomainException::replacementPlanInvalid([(int) $passenger->id]);
            }
        }

        $pilgrimIds = $passengers->pluck('season_pilgrim_id')->map(fn ($id): int => (int) $id)->unique()->sort()->values()->all();
        $seasonPilgrims = SeasonPilgrim::query()
            ->where('season_id', $sourceRun->season_id)
            ->whereKey($pilgrimIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $moves = [];
        $now = Carbon::now();

        foreach ($plan as $row) {
            $passengerId = (int) $row['run_passenger_id'];
            $targetRunId = (int) $row['target_run_id'];
            /** @var TransportRunPassenger $passenger */
            $passenger = $passengers->get($passengerId);
            /** @var TransportVehicleRun $targetRun */
            $targetRun = $lockedTargets[$targetRunId];

            $targetSvId = (int) $targetRun->currentVehicleAssignment()->lockForUpdate()->value('transport_season_vehicle_id');
            /** @var TransportSeasonVehicle $targetSv */
            $targetSv = TransportSeasonVehicle::query()->whereKey($targetSvId)->lockForUpdate()->firstOrFail();
            $targetSv->load('vehicle');
            $capacity = $targetRun->capacity_override ?? $targetSv->operational_capacity;
            $targetOccupying = TransportRunPassenger::query()
                ->where('vehicle_run_id', $targetRun->id)
                ->whereIn('status', PassengerStatus::occupyingValues())
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id', 'season_pilgrim_id']);
            if ($targetOccupying->count() + 1 > $capacity) {
                throw TransportationDomainException::capacityExceeded((int) $capacity, $targetOccupying->count(), 1, [(int) $passenger->season_pilgrim_id]);
            }

            $already = $targetOccupying->contains(
                fn ($row): bool => (int) $row->season_pilgrim_id === (int) $passenger->season_pilgrim_id,
            );
            if ($already) {
                throw TransportationDomainException::passengerDuplicate([(int) $passenger->season_pilgrim_id]);
            }

            /** @var SeasonPilgrim|null $sp */
            $sp = $seasonPilgrims->get($passenger->season_pilgrim_id);
            if ($sp === null || $sp->status !== SeasonPilgrimStatus::Active) {
                throw TransportationDomainException::passengerNotEligible([(int) $passenger->season_pilgrim_id]);
            }

            $entitlementStatus = $this->entitlements->evaluate($sp->season_program_id, $targetSv);
            if ($entitlementStatus === EntitlementStatus::Mismatch) {
                throw TransportationDomainException::entitlementMismatch([(int) $passenger->season_pilgrim_id]);
            }

            $this->cancellation->cancel(
                $sourceRun,
                $passenger,
                $actor->id,
                'إعادة توزيع بسبب استبدال مركبة بعد عطل',
                ['via' => 'breakdown_replacement', 'target_run_id' => $targetRunId],
            );

            $entitlement = $this->entitlements->entitlementFor($sp->season_program_id);
            $programCode = $sp->season_program_id !== null
                ? SeasonProgram::query()->whereKey($sp->season_program_id)->value('code')
                : null;

            $newPassenger = TransportRunPassenger::query()->create([
                'season_id' => $targetRun->season_id,
                'vehicle_run_id' => $targetRun->id,
                'season_pilgrim_id' => $passenger->season_pilgrim_id,
                'status' => PassengerStatus::Assigned,
                'assignment_source' => AssignmentSource::Individual,
                'source_group_id' => null,
                'source_group_label' => null,
                'program_id_at_assignment' => $sp->season_program_id,
                'program_code_at_assignment' => $programCode,
                'entitlement_id_at_assignment' => $entitlement?->id,
                'entitlement_vehicle_class' => $entitlement?->vehicle_class?->value,
                'entitlement_service_level' => $entitlement?->service_level?->value,
                'season_vehicle_id_at_assignment' => $targetSv->id,
                'vehicle_class_at_assignment' => $targetSv->vehicle?->vehicle_class?->value,
                'service_level_at_assignment' => $targetSv->service_level?->value,
                'entitlement_status_at_assignment' => $entitlementStatus,
                'override_at_assignment' => false,
                'assigned_at' => $now,
                'assigned_by' => $actor->id,
            ]);

            $this->timeline->record(
                seasonId: $targetRun->season_id,
                movementId: $targetRun->transport_movement_id,
                type: TimelineEventType::PassengerAssigned,
                occurredAt: $now,
                actorId: $actor->id,
                runId: $targetRun->id,
                passengerId: $newPassenger->id,
                context: [
                    'season_pilgrim_id' => $passenger->season_pilgrim_id,
                    'via' => 'breakdown_replacement',
                    'source_passenger_id' => $passenger->id,
                ],
            );

            $this->audit->record(AuthorizationSecurityEvent::TRANSPORT_PASSENGER_CANCELLED, $actor->id, 'transport_run_passenger', $passenger->id, null, null, [
                'reason' => 'إعادة توزيع بسبب استبدال مركبة بعد عطل',
                'metadata' => ['season_id' => $sourceRun->season_id, 'via' => 'breakdown_replacement'],
            ], $request);
            $this->audit->record(AuthorizationSecurityEvent::TRANSPORT_PASSENGERS_ADDED, $actor->id, 'transport_vehicle_run', $targetRun->id, null, null, [
                'metadata' => [
                    'season_id' => $targetRun->season_id,
                    'season_pilgrim_ids' => [(int) $passenger->season_pilgrim_id],
                    'via' => 'breakdown_replacement',
                ],
            ], $request);

            $moves[] = [
                'from_run_id' => $sourceRun->id,
                'to_run_id' => $targetRun->id,
                'season_pilgrim_id' => (int) $passenger->season_pilgrim_id,
                'source_passenger_id' => $passenger->id,
                'target_passenger_id' => $newPassenger->id,
            ];
        }

        return $moves;
    }
}
