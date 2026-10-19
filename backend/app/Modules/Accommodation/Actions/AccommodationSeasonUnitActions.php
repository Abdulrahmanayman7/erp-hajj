<?php

namespace App\Modules\Accommodation\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Models\User;
use App\Modules\Accommodation\Exceptions\AccommodationDomainException;
use App\Modules\Accommodation\Models\AccommodationSeasonFacility;
use App\Modules\Accommodation\Models\AccommodationSeasonUnit;
use App\Modules\Accommodation\Models\AccommodationUnit;
use App\Modules\Accommodation\Support\AccommodationAudit;
use App\Modules\Accommodation\Support\AccommodationCapacityGuard;
use App\Modules\Accommodation\Support\AccommodationLockOrder;
use App\Modules\Accommodation\Support\AccommodationPersistence;
use App\Modules\Accommodation\Support\AccommodationTransaction;
use App\Modules\SeasonPilgrims\Context\SeasonContext;
use App\Modules\SeasonPilgrims\Support\SeasonGuard;
use Illuminate\Http\Request;

final class AccommodationSeasonUnitActions
{
    public function __construct(
        private readonly AccommodationAudit $audit,
        private readonly AccommodationCapacityGuard $capacity,
        private readonly AccommodationLockOrder $locks,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data, Request $request): AccommodationSeasonUnit
    {
        return AccommodationTransaction::run(function () use ($actor, $data, $request): AccommodationSeasonUnit {
            $season = app(SeasonContext::class)->require();
            SeasonGuard::assertWritable($season);
            $seasonFacility = AccommodationSeasonFacility::query()->where('season_id', $season->id)->findOrFail($data['season_facility_id']);
            $unit = AccommodationUnit::query()->findOrFail($data['unit_id']);

            if ((int) $unit->facility_id !== (int) $seasonFacility->facility_id) {
                throw AccommodationDomainException::invalidReference();
            }

            $row = AccommodationPersistence::save(fn () => AccommodationSeasonUnit::query()->create([
                'season_id' => $season->id,
                'season_facility_id' => $seasonFacility->id,
                'facility_id' => $seasonFacility->facility_id,
                'unit_id' => $unit->id,
                'allocation_mode' => $data['allocation_mode'],
                'capacity' => $data['capacity'] ?? null,
                'audience' => $data['audience'],
                'is_active' => $data['is_active'] ?? true,
                'created_by' => $actor->id,
            ]));

            $this->audit->record(AuthorizationSecurityEvent::ACCOMMODATION_SEASON_UNIT_CREATED, $actor->id, 'accommodation_season_unit', $row->id, $unit->code, null, [
                'after_values' => [
                    'unit_id' => $unit->id,
                    'allocation_mode' => $row->allocation_mode,
                    'capacity' => $row->capacity,
                    'audience' => $row->audience,
                    'capacity_reserved' => false,
                ],
            ], $request);

            return $row;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, AccommodationSeasonUnit $row, array $data, Request $request): AccommodationSeasonUnit
    {
        return AccommodationTransaction::run(function () use ($actor, $row, $data, $request): AccommodationSeasonUnit {
            SeasonGuard::assertWritable(app(SeasonContext::class)->require());
            $this->locks->lockUnits([(int) $row->unit_id]);
            $locked = $this->locks->lockSeasonUnits([(int) $row->id])->get($row->id)
                ?? AccommodationSeasonUnit::query()->whereKey($row->id)->lockForUpdate()->firstOrFail();

            $newCapacity = array_key_exists('capacity', $data) ? $data['capacity'] : $locked->capacity;
            $newActive = array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $locked->is_active;

            if (array_key_exists('capacity', $data) && (int) $newCapacity !== (int) $locked->capacity) {
                $this->capacity->assertCapacityDecreaseAllowed($locked, $newCapacity === null ? null : (int) $newCapacity);
            }

            if ($locked->is_active && ! $newActive) {
                $this->capacity->assertNoConfirmedOnSeasonUnit((int) $locked->id);
            }

            $locked->fill([
                'allocation_mode' => $data['allocation_mode'] ?? $locked->allocation_mode,
                'capacity' => array_key_exists('capacity', $data) ? $data['capacity'] : $locked->capacity,
                'audience' => $data['audience'] ?? $locked->audience,
                'is_active' => $newActive,
            ]);
            AccommodationPersistence::save(fn () => $locked->save());
            $this->audit->record(AuthorizationSecurityEvent::ACCOMMODATION_SEASON_UNIT_UPDATED, $actor->id, 'accommodation_season_unit', $locked->id, null, null, [
                'after_values' => ['allocation_mode' => $locked->allocation_mode, 'capacity' => $locked->capacity, 'audience' => $locked->audience, 'is_active' => $locked->is_active, 'capacity_reserved' => false],
            ], $request);

            return $locked;
        });
    }

    public function delete(User $actor, AccommodationSeasonUnit $row, Request $request): void
    {
        AccommodationTransaction::run(function () use ($actor, $row, $request): void {
            SeasonGuard::assertWritable(app(SeasonContext::class)->require());
            $this->locks->lockUnits([(int) $row->unit_id]);
            $locked = $this->locks->lockSeasonUnits([(int) $row->id])->get($row->id)
                ?? AccommodationSeasonUnit::query()->whereKey($row->id)->lockForUpdate()->firstOrFail();
            $this->capacity->assertNoConfirmedOnSeasonUnit((int) $locked->id);
            $this->audit->record(AuthorizationSecurityEvent::ACCOMMODATION_SEASON_UNIT_DELETED, $actor->id, 'accommodation_season_unit', $locked->id, null, null, [], $request);
            AccommodationPersistence::save(fn () => $locked->delete());
        });
    }
}
