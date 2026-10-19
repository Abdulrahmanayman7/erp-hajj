<?php

namespace App\Modules\Accommodation\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Models\User;
use App\Modules\Accommodation\Models\AccommodationPosition;
use App\Modules\Accommodation\Models\AccommodationUnit;
use App\Modules\Accommodation\Support\AccommodationAudit;
use App\Modules\Accommodation\Support\AccommodationPersistence;
use App\Modules\SeasonPilgrims\Context\SeasonContext;
use App\Modules\SeasonPilgrims\Support\SeasonGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AccommodationPositionActions
{
    public function __construct(private readonly AccommodationAudit $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, AccommodationUnit $unit, array $data, Request $request): AccommodationPosition
    {
        return DB::transaction(function () use ($actor, $unit, $data, $request): AccommodationPosition {
            SeasonGuard::assertWritable(app(SeasonContext::class)->require());

            $position = AccommodationPersistence::save(fn () => AccommodationPosition::query()->create([
                'unit_id' => $unit->id,
                'code' => trim((string) $data['code']),
                'created_by' => $actor->id,
            ]));

            $this->audit->record(AuthorizationSecurityEvent::ACCOMMODATION_POSITION_CREATED, $actor->id, 'accommodation_position', $position->id, $position->code, null, [
                'after_values' => ['unit_id' => $unit->id, 'code' => $position->code],
            ], $request);

            return $position;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, AccommodationPosition $position, array $data, Request $request): AccommodationPosition
    {
        return DB::transaction(function () use ($actor, $position, $data, $request): AccommodationPosition {
            SeasonGuard::assertWritable(app(SeasonContext::class)->require());
            $locked = AccommodationPosition::query()->whereKey($position->id)->lockForUpdate()->firstOrFail();
            $locked->code = trim((string) ($data['code'] ?? $locked->code));
            AccommodationPersistence::save(fn () => $locked->save());

            $this->audit->record(AuthorizationSecurityEvent::ACCOMMODATION_POSITION_UPDATED, $actor->id, 'accommodation_position', $locked->id, $locked->code, null, [
                'after_values' => ['code' => $locked->code],
            ], $request);

            return $locked;
        });
    }

    public function delete(User $actor, AccommodationPosition $position, Request $request): void
    {
        DB::transaction(function () use ($actor, $position, $request): void {
            SeasonGuard::assertWritable(app(SeasonContext::class)->require());
            $locked = AccommodationPosition::query()->whereKey($position->id)->lockForUpdate()->firstOrFail();
            $this->audit->record(AuthorizationSecurityEvent::ACCOMMODATION_POSITION_DELETED, $actor->id, 'accommodation_position', $locked->id, $locked->code, null, [], $request);
            AccommodationPersistence::save(fn () => $locked->delete());
        });
    }
}
