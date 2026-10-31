<?php

namespace App\Modules\Transportation\Actions\Breakdowns;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Models\User;
use App\Modules\SeasonPilgrims\Support\SeasonGuard;
use App\Modules\Transportation\Enums\BreakdownStatus;
use App\Modules\Transportation\Enums\TimelineEventType;
use App\Modules\Transportation\Exceptions\TransportationDomainException;
use App\Modules\Transportation\Models\TransportVehicleBreakdown;
use App\Modules\Transportation\Models\TransportVehicleRun;
use App\Modules\Transportation\Support\OccurredAtResolver;
use App\Modules\Transportation\Support\RunLocks;
use App\Modules\Transportation\Support\TimelineRecorder;
use App\Modules\Transportation\Support\TransportAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Records an open operational breakdown against the Run's current vehicle.
 */
final class RecordBreakdown
{
    public function __construct(
        private readonly TimelineRecorder $timeline,
        private readonly TransportAudit $audit,
        private readonly OccurredAtResolver $occurredAt,
    ) {}

    /**
     * @param  array{reason: string, notes?: string|null, occurred_at?: string|null}  $data
     */
    public function execute(User $actor, TransportVehicleRun $run, array $data, Request $request): TransportVehicleBreakdown
    {
        return DB::transaction(function () use ($actor, $run, $data, $request): TransportVehicleBreakdown {
            [$movement, $locked] = RunLocks::lock($run->id);
            SeasonGuard::assertWritable($movement->season()->firstOrFail());

            if ($locked->status->isTerminal()) {
                throw TransportationDomainException::breakdownRunTerminal();
            }

            $current = $locked->currentVehicleAssignment()->lockForUpdate()->first();
            if ($current === null) {
                throw TransportationDomainException::linkInvalid('transport_season_vehicle_id');
            }

            $occurred = $this->occurredAt->resolve(
                $actor,
                $data['occurred_at'] ?? null,
                $data['reason'] ?? null,
            );

            $breakdown = TransportVehicleBreakdown::query()->create([
                'season_id' => $locked->season_id,
                'vehicle_run_id' => $locked->id,
                'transport_season_vehicle_id' => $current->transport_season_vehicle_id,
                'status' => BreakdownStatus::Open,
                'occurred_at' => $occurred->at,
                'reason' => trim((string) $data['reason']),
                'notes' => isset($data['notes']) && trim((string) $data['notes']) !== '' ? trim((string) $data['notes']) : null,
                'reported_by' => $actor->id,
            ]);

            $this->timeline->record(
                seasonId: $locked->season_id,
                movementId: $movement->id,
                type: TimelineEventType::BreakdownRecorded,
                occurredAt: $occurred->at,
                actorId: $actor->id,
                runId: $locked->id,
                reason: $breakdown->reason,
                context: [
                    'breakdown_id' => $breakdown->id,
                    'transport_season_vehicle_id' => $breakdown->transport_season_vehicle_id,
                ],
            );

            $this->audit->record(
                AuthorizationSecurityEvent::TRANSPORT_BREAKDOWN_RECORDED,
                $actor->id,
                'transport_vehicle_breakdown',
                $breakdown->id,
                $movement->movement_number.'/'.$locked->run_number,
                null,
                [
                    'after_values' => [
                        'status' => BreakdownStatus::Open->value,
                        'vehicle_run_id' => $locked->id,
                        'transport_season_vehicle_id' => $breakdown->transport_season_vehicle_id,
                    ],
                    'reason' => $breakdown->reason,
                    'metadata' => ['season_id' => $locked->season_id],
                ],
                $request,
            );

            return $breakdown->fresh();
        }, 5);
    }
}
