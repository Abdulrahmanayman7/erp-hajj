<?php

namespace App\Modules\Accommodation\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Models\User;
use App\Modules\Accommodation\Exceptions\AccommodationDomainException;
use App\Modules\Accommodation\Models\AccommodationAssignment;
use App\Modules\Accommodation\Models\AccommodationSeasonPosition;
use App\Modules\Accommodation\Models\AccommodationSeasonUnit;
use App\Modules\Accommodation\Support\AccommodationAccess;
use App\Modules\Accommodation\Support\AccommodationAudit;
use App\Modules\Accommodation\Support\AccommodationPersistence;
use App\Modules\SeasonPilgrims\Context\SeasonContext;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrim;
use App\Modules\SeasonPilgrims\Support\SeasonGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AccommodationAssignmentActions
{
    public function __construct(
        private readonly AccommodationAudit $audit,
        private readonly AccommodationAccess $access,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data, Request $request): AccommodationAssignment
    {
        return DB::transaction(function () use ($actor, $data, $request): AccommodationAssignment {
            $season = app(SeasonContext::class)->require();
            SeasonGuard::assertWritable($season);
            $seasonUnit = AccommodationSeasonUnit::query()->where('season_id', $season->id)->findOrFail($data['season_unit_id']);
            $this->access->lockAndAssert($actor, (int) $seasonUnit->facility_id);
            SeasonPilgrim::query()->where('season_id', $season->id)->findOrFail($data['season_pilgrim_id']);
            $positionId = $this->positionId($season->id, $seasonUnit, $data['season_position_id'] ?? null);
            [$start, $end] = AccommodationPersistence::utcInterval((string) $data['starts_at'], (string) $data['original_ends_at']);

            $row = AccommodationPersistence::save(fn () => AccommodationAssignment::query()->create([
                'season_id' => $season->id,
                'season_pilgrim_id' => $data['season_pilgrim_id'],
                'season_unit_id' => $seasonUnit->id,
                'unit_id' => $seasonUnit->unit_id,
                'season_position_id' => $positionId,
                'booking_status' => 'draft',
                'operational_status' => null,
                'starts_at' => $start,
                'original_ends_at' => $end,
                'effective_ends_at' => $end,
                'family_group_id' => null,
                'checkout_source' => null,
                'created_by' => $actor->id,
            ]));

            $this->audit->record(AuthorizationSecurityEvent::ACCOMMODATION_ASSIGNMENT_CREATED, $actor->id, 'accommodation_assignment', $row->id, null, null, [
                'after_values' => ['booking_status' => 'draft', 'season_unit_id' => $seasonUnit->id, 'starts_at' => $start, 'original_ends_at' => $end, 'capacity_reserved' => false],
            ], $request);

            return $row;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, AccommodationAssignment $assignment, array $data, Request $request): AccommodationAssignment
    {
        return DB::transaction(function () use ($actor, $assignment, $data, $request): AccommodationAssignment {
            $season = app(SeasonContext::class)->require();
            SeasonGuard::assertWritable($season);
            $locked = AccommodationAssignment::query()->where('season_id', $season->id)->whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            $seasonUnit = array_key_exists('season_unit_id', $data)
                ? AccommodationSeasonUnit::query()->where('season_id', $season->id)->findOrFail($data['season_unit_id'])
                : AccommodationSeasonUnit::query()->where('season_id', $season->id)->findOrFail($locked->season_unit_id);
            $this->access->lockAndAssert($actor, (int) $seasonUnit->facility_id);

            $positionId = array_key_exists('season_position_id', $data)
                ? $this->positionId($season->id, $seasonUnit, $data['season_position_id'])
                : $locked->season_position_id;

            if (! array_key_exists('season_position_id', $data) && (int) $seasonUnit->id !== (int) $locked->season_unit_id) {
                $positionId = null;
            }

            if ($positionId !== null) {
                $positionId = $this->positionId($season->id, $seasonUnit, $positionId);
            }

            $start = array_key_exists('starts_at', $data) ? (string) $data['starts_at'] : $locked->starts_at->utc()->toIso8601String();
            $end = array_key_exists('original_ends_at', $data) ? (string) $data['original_ends_at'] : $locked->original_ends_at->utc()->toIso8601String();
            [$start, $end] = AccommodationPersistence::utcInterval($start, $end);

            $locked->fill([
                'season_unit_id' => $seasonUnit->id,
                'unit_id' => $seasonUnit->unit_id,
                'season_position_id' => $positionId,
                'booking_status' => 'draft',
                'operational_status' => null,
                'starts_at' => $start,
                'original_ends_at' => $end,
                'effective_ends_at' => $end,
                'family_group_id' => null,
                'checkout_source' => null,
            ]);
            AccommodationPersistence::save(fn () => $locked->save());

            $this->audit->record(AuthorizationSecurityEvent::ACCOMMODATION_ASSIGNMENT_UPDATED, $actor->id, 'accommodation_assignment', $locked->id, null, null, [
                'after_values' => ['season_unit_id' => $locked->season_unit_id, 'starts_at' => $start, 'original_ends_at' => $end, 'capacity_reserved' => false],
            ], $request);

            return $locked;
        });
    }

    public function delete(User $actor, AccommodationAssignment $assignment, Request $request): void
    {
        DB::transaction(function () use ($actor, $assignment, $request): void {
            $season = app(SeasonContext::class)->require();
            SeasonGuard::assertWritable($season);
            $locked = AccommodationAssignment::query()->where('season_id', $season->id)->whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            $seasonUnit = AccommodationSeasonUnit::query()->where('season_id', $season->id)->findOrFail($locked->season_unit_id);
            $this->access->lockAndAssert($actor, (int) $seasonUnit->facility_id);
            $this->audit->record(AuthorizationSecurityEvent::ACCOMMODATION_ASSIGNMENT_DELETED, $actor->id, 'accommodation_assignment', $locked->id, null, null, [], $request);
            AccommodationPersistence::save(fn () => $locked->delete());
        });
    }

    private function positionId(int $seasonId, AccommodationSeasonUnit $seasonUnit, mixed $positionId): ?int
    {
        if ($positionId === null) {
            return null;
        }

        $position = AccommodationSeasonPosition::query()->where('season_id', $seasonId)->findOrFail($positionId);

        if ((int) $position->season_unit_id !== (int) $seasonUnit->id) {
            throw AccommodationDomainException::invalidReference();
        }

        return (int) $position->id;
    }
}
