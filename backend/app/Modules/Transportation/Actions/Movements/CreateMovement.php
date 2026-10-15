<?php

namespace App\Modules\Transportation\Actions\Movements;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Models\User;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonPilgrims\Support\SeasonGuard;
use App\Modules\Transportation\Enums\MovementStatus;
use App\Modules\Transportation\Enums\TimelineEventType;
use App\Modules\Transportation\Models\TransportMovement;
use App\Modules\Transportation\Support\TimelineRecorder;
use App\Modules\Transportation\Support\TransportAudit;
use App\Modules\Transportation\Support\TransportNumberGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class CreateMovement
{
    public function __construct(
        private readonly TransportNumberGenerator $numbers,
        private readonly TimelineRecorder $timeline,
        private readonly TransportAudit $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, Season $season, array $data, Request $request): TransportMovement
    {
        return DB::transaction(function () use ($actor, $season, $data, $request): TransportMovement {
            SeasonGuard::assertWritable($season);

            $originId = (int) $data['origin_point_id'];
            $destinationId = (int) $data['destination_point_id'];
            $departure = Carbon::parse((string) $data['planned_departure_at']);
            $arrival = Carbon::parse((string) $data['planned_arrival_at']);

            MovementPoints::assertWindow($departure, $arrival);
            MovementPoints::assertValid($originId, $destinationId);

            $movement = TransportMovement::query()->create([
                'season_id' => $season->id,
                'movement_number' => $this->numbers->nextMovementNumber($season),
                'origin_point_id' => $originId,
                'destination_point_id' => $destinationId,
                'planned_departure_at' => $departure,
                'planned_arrival_at' => $arrival,
                'status' => MovementStatus::Draft,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);

            $this->timeline->record(
                seasonId: $season->id,
                movementId: $movement->id,
                type: TimelineEventType::MovementCreated,
                actorId: $actor->id,
            );

            $this->audit->record(AuthorizationSecurityEvent::TRANSPORT_MOVEMENT_CREATED, $actor->id, 'transport_movement', $movement->id, $movement->movement_number, null, [
                'after_values' => TransportAudit::values($movement->only([
                    'origin_point_id', 'destination_point_id', 'planned_departure_at', 'planned_arrival_at', 'status',
                ])),
                'metadata' => ['season_id' => $season->id],
            ], $request);

            return MovementLoader::load($movement);
        });
    }
}
