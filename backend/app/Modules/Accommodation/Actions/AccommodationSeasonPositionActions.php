<?php

namespace App\Modules\Accommodation\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Models\User;
use App\Modules\Accommodation\Exceptions\AccommodationDomainException;
use App\Modules\Accommodation\Models\AccommodationPosition;
use App\Modules\Accommodation\Models\AccommodationSeasonPosition;
use App\Modules\Accommodation\Models\AccommodationSeasonUnit;
use App\Modules\Accommodation\Support\AccommodationAudit;
use App\Modules\Accommodation\Support\AccommodationCapacityGuard;
use App\Modules\Accommodation\Support\AccommodationLockOrder;
use App\Modules\Accommodation\Support\AccommodationPersistence;
use App\Modules\Accommodation\Support\AccommodationTransaction;
use App\Modules\SeasonPilgrims\Context\SeasonContext;
use App\Modules\SeasonPilgrims\Support\SeasonGuard;
use Illuminate\Http\Request;

final class AccommodationSeasonPositionActions
{
    public function __construct(
        private readonly AccommodationAudit $audit,
        private readonly AccommodationCapacityGuard $capacity,
        private readonly AccommodationLockOrder $locks,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data, Request $request): AccommodationSeasonPosition
    {
        return AccommodationTransaction::run(function () use ($actor, $data, $request): AccommodationSeasonPosition {
            $season = app(SeasonContext::class)->require();
            SeasonGuard::assertWritable($season);
            $seasonUnit = AccommodationSeasonUnit::query()->where('season_id', $season->id)->findOrFail($data['season_unit_id']);
            $position = AccommodationPosition::query()->findOrFail($data['position_id']);

            if ((int) $position->unit_id !== (int) $seasonUnit->unit_id) {
                throw AccommodationDomainException::invalidReference();
            }

            $row = AccommodationPersistence::save(fn () => AccommodationSeasonPosition::query()->create([
                'season_id' => $season->id,
                'season_unit_id' => $seasonUnit->id,
                'unit_id' => $seasonUnit->unit_id,
                'position_id' => $position->id,
                'is_active' => $data['is_active'] ?? true,
                'created_by' => $actor->id,
            ]));

            $this->audit->record(AuthorizationSecurityEvent::ACCOMMODATION_SEASON_POSITION_CREATED, $actor->id, 'accommodation_season_position', $row->id, $position->code, null, [
                'after_values' => ['season_unit_id' => $seasonUnit->id, 'position_id' => $position->id],
            ], $request);

            return $row;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, AccommodationSeasonPosition $row, array $data, Request $request): AccommodationSeasonPosition
    {
        return AccommodationTransaction::run(function () use ($actor, $row, $data, $request): AccommodationSeasonPosition {
            SeasonGuard::assertWritable(app(SeasonContext::class)->require());
            $seasonUnit = AccommodationSeasonUnit::query()->whereKey($row->season_unit_id)->firstOrFail();
            $this->locks->lockUnits([(int) $seasonUnit->unit_id]);
            $this->locks->lockSeasonUnits([(int) $seasonUnit->id]);
            $locked = $this->locks->lockSeasonPositions([(int) $row->id])->get($row->id)
                ?? AccommodationSeasonPosition::query()->whereKey($row->id)->lockForUpdate()->firstOrFail();

            if (array_key_exists('is_active', $data) && $locked->is_active && ! (bool) $data['is_active']) {
                $this->capacity->assertEnabledPositionDecreaseAllowed($seasonUnit, (int) $locked->id);
                $locked->is_active = false;
            } elseif (array_key_exists('is_active', $data)) {
                $locked->is_active = (bool) $data['is_active'];
            }

            AccommodationPersistence::save(fn () => $locked->save());
            $this->audit->record(AuthorizationSecurityEvent::ACCOMMODATION_SEASON_POSITION_UPDATED, $actor->id, 'accommodation_season_position', $locked->id, null, null, [
                'after_values' => ['is_active' => $locked->is_active],
            ], $request);

            return $locked;
        });
    }

    public function delete(User $actor, AccommodationSeasonPosition $row, Request $request): void
    {
        AccommodationTransaction::run(function () use ($actor, $row, $request): void {
            SeasonGuard::assertWritable(app(SeasonContext::class)->require());
            $seasonUnit = AccommodationSeasonUnit::query()->whereKey($row->season_unit_id)->firstOrFail();
            $this->locks->lockUnits([(int) $seasonUnit->unit_id]);
            $this->locks->lockSeasonUnits([(int) $seasonUnit->id]);
            $locked = $this->locks->lockSeasonPositions([(int) $row->id])->get($row->id)
                ?? AccommodationSeasonPosition::query()->whereKey($row->id)->lockForUpdate()->firstOrFail();
            $this->capacity->assertEnabledPositionDecreaseAllowed($seasonUnit, (int) $locked->id);
            $this->audit->record(AuthorizationSecurityEvent::ACCOMMODATION_SEASON_POSITION_DELETED, $actor->id, 'accommodation_season_position', $locked->id, null, null, [], $request);
            AccommodationPersistence::save(fn () => $locked->delete());
        });
    }
}
