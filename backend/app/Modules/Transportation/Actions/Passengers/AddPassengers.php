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
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * ADR-0018 T5 / T9 / I6: materializes one passenger row per Active
 * SeasonPilgrim. Every source (individual / group / family) is
 * all-or-nothing: membership is resolved, every member is validated, then
 * rows are inserted inside one transaction. Group / family source id +
 * label are snapshotted with no FK. Capacity is count-based with no
 * overbooking. Assignment-time Program / entitlement / vehicle context is
 * written once and never rewritten.
 *
 * Lock order: movement → run → season vehicle → season pilgrims (by id).
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

        return DB::transaction(function () use ($actor, $run, $source, $seasonPilgrimIds, $sourceId, $override, $overrideReason, $request): array {
            [$movement, $locked] = RunLocks::lock($run->id);
            SeasonGuard::assertWritable($movement->season()->firstOrFail());

            if ($movement->status->isTerminal() || ! in_array($locked->status, [RunStatus::Planned, RunStatus::Boarding], true)) {
                throw TransportationDomainException::manifestLocked();
            }

            $seasonVehicleId = (int) $locked->currentVehicleAssignment()->lockForUpdate()->value('transport_season_vehicle_id');
            /** @var TransportSeasonVehicle $seasonVehicle */
            $seasonVehicle = TransportSeasonVehicle::query()->whereKey($seasonVehicleId)->lockForUpdate()->firstOrFail();
            $seasonVehicle->load('vehicle');
            $capacity = $locked->capacity_override ?? $seasonVehicle->operational_capacity;
            $vehicleClass = $seasonVehicle->vehicle?->vehicle_class?->value;
            $serviceLevel = $seasonVehicle->service_level?->value;

            [$candidateIds, $sourceLabel] = $this->resolveSource($locked->season_id, $source, $seasonPilgrimIds, $sourceId);

            $seasonPilgrims = SeasonPilgrim::query()
                ->where('season_id', $locked->season_id)
                ->whereKey($candidateIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            // Locking occupancy read: after waiting on run locks under REPEATABLE
            // READ, a plain COUNT can still see the pre-wait snapshot and allow
            // overbooking. FOR UPDATE forces the winner's committed rows.
            $occupying = TransportRunPassenger::query()
                ->where('vehicle_run_id', $locked->id)
                ->whereIn('status', PassengerStatus::occupyingValues())
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id', 'season_pilgrim_id']);

            $alreadyOnRun = $occupying
                ->whereIn('season_pilgrim_id', $candidateIds)
                ->pluck('season_pilgrim_id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            $notEligible = array_values(array_filter(
                $candidateIds,
                fn (int $id): bool => ! $seasonPilgrims->has($id) || $seasonPilgrims->get($id)->status !== SeasonPilgrimStatus::Active,
            ));
            if ($notEligible !== []) {
                throw TransportationDomainException::passengerNotEligible($notEligible);
            }

            $duplicates = array_values(array_intersect($candidateIds, $alreadyOnRun));
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

            $occupied = $occupying->count();
            if ($occupied + count($candidateIds) > $capacity) {
                throw TransportationDomainException::capacityExceeded($capacity, $occupied, count($candidateIds), $candidateIds);
            }

            $programCodes = SeasonProgram::query()
                ->whereKey($seasonPilgrims->pluck('season_program_id')->filter()->unique()->all())
                ->pluck('code', 'id');

            $now = Carbon::now();
            $added = [];

            foreach ($candidateIds as $id) {
                $seasonPilgrim = $seasonPilgrims->get($id);
                $programId = $seasonPilgrim->season_program_id;
                $entitlement = $this->entitlements->entitlementFor($programId);
                $isOverride = $statuses[$id] === EntitlementStatus::Mismatch;
                $statusAtAssignment = $isOverride ? EntitlementStatus::Overridden : $statuses[$id];

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
                        'entitlement_vehicle_class' => $entitlement?->vehicle_class?->value,
                        'entitlement_service_level' => $entitlement?->service_level?->value,
                        'season_vehicle_id_at_assignment' => $seasonVehicle->id,
                        'vehicle_class_at_assignment' => $vehicleClass,
                        'service_level_at_assignment' => $serviceLevel,
                        'entitlement_status_at_assignment' => $statusAtAssignment,
                        'override_at_assignment' => $isOverride,
                        'override_reason_at_assignment' => $isOverride ? $overrideReason : null,
                        'overridden_by_at_assignment' => $isOverride ? $actor->id : null,
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
                    context: [
                        'season_pilgrim_id' => $id,
                        'assignment_source' => $source->value,
                        'source_group_id' => $source === AssignmentSource::Individual ? null : $sourceId,
                        'entitlement_status' => $passenger->entitlement_status_at_assignment->value,
                        'entitlement_id' => $passenger->entitlement_id_at_assignment,
                    ],
                );

                $added[] = $passenger;
            }

            $runLabel = $movement->movement_number.'/'.$locked->run_number;
            $this->audit->record(AuthorizationSecurityEvent::TRANSPORT_PASSENGERS_ADDED, $actor->id, 'transport_vehicle_run', $locked->id, $runLabel, null, [
                'metadata' => [
                    'season_id' => $locked->season_id,
                    'assignment_source' => $source->value,
                    'source_group_id' => $source === AssignmentSource::Individual ? null : $sourceId,
                    'season_pilgrim_ids' => $candidateIds,
                ],
            ], $request);

            if ($mismatched !== []) {
                $this->audit->record(AuthorizationSecurityEvent::TRANSPORT_PASSENGER_ENTITLEMENT_OVERRIDDEN, $actor->id, 'transport_vehicle_run', $locked->id, $runLabel, null, [
                    'reason' => $overrideReason,
                    'metadata' => ['season_id' => $locked->season_id, 'season_pilgrim_ids' => $mismatched, 'at_assignment' => true],
                ], $request);
            }

            return ['run' => $locked, 'added' => count($added)];
        }, 5);
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
