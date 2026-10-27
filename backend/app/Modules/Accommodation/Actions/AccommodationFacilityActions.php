<?php

namespace App\Modules\Accommodation\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Models\User;
use App\Modules\Accommodation\Models\AccommodationFacility;
use App\Modules\Accommodation\Support\AccommodationAudit;
use App\Modules\Accommodation\Support\AccommodationCodeGenerator;
use App\Modules\Accommodation\Support\AccommodationPersistence;
use App\Modules\SeasonPilgrims\Context\SeasonContext;
use App\Modules\SeasonPilgrims\Support\SeasonGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AccommodationFacilityActions
{
    public function __construct(
        private readonly AccommodationAudit $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data, Request $request): AccommodationFacility
    {
        return DB::transaction(function () use ($actor, $data, $request): AccommodationFacility {
            SeasonGuard::assertWritable(app(SeasonContext::class)->require());

            $facility = AccommodationPersistence::save(fn () => AccommodationFacility::query()->create([
                'kind' => $data['kind'],
                'code' => isset($data['code']) && trim((string) $data['code']) !== ''
                    ? trim((string) $data['code'])
                    : app(AccommodationCodeGenerator::class)->next($data['kind']),
                'name' => trim((string) $data['name']),
                'is_active' => true,
                'created_by' => $actor->id,
            ]));

            $this->audit->record(AuthorizationSecurityEvent::ACCOMMODATION_FACILITY_CREATED, $actor->id, 'accommodation_facility', $facility->id, $facility->code, $facility->name, [
                'after_values' => AccommodationAudit::values(['kind' => $facility->kind, 'code' => $facility->code, 'name' => $facility->name]),
            ], $request);

            return $facility;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, AccommodationFacility $facility, array $data, Request $request): AccommodationFacility
    {
        return DB::transaction(function () use ($actor, $facility, $data, $request): AccommodationFacility {
            SeasonGuard::assertWritable(app(SeasonContext::class)->require());
            $locked = AccommodationFacility::query()->whereKey($facility->id)->lockForUpdate()->firstOrFail();
            $beforeActive = (bool) $locked->is_active;

            $locked->fill([
                'kind' => $data['kind'] ?? $locked->kind,
                'code' => array_key_exists('code', $data) ? trim((string) $data['code']) : $locked->code,
                'name' => array_key_exists('name', $data) ? trim((string) $data['name']) : $locked->name,
                'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $locked->is_active,
            ]);
            AccommodationPersistence::save(fn () => $locked->save());

            $this->audit->record(AuthorizationSecurityEvent::ACCOMMODATION_FACILITY_UPDATED, $actor->id, 'accommodation_facility', $locked->id, $locked->code, $locked->name, [
                'after_values' => AccommodationAudit::values(['kind' => $locked->kind, 'code' => $locked->code, 'name' => $locked->name, 'is_active' => $locked->is_active]),
            ], $request);

            if ($beforeActive && ! $locked->is_active) {
                $this->audit->record(AuthorizationSecurityEvent::ACCOMMODATION_FACILITY_DEACTIVATED, $actor->id, 'accommodation_facility', $locked->id, $locked->code, $locked->name, [], $request);
            }

            return $locked;
        });
    }

    public function delete(User $actor, AccommodationFacility $facility, Request $request): void
    {
        DB::transaction(function () use ($actor, $facility, $request): void {
            SeasonGuard::assertWritable(app(SeasonContext::class)->require());
            $locked = AccommodationFacility::query()->whereKey($facility->id)->lockForUpdate()->firstOrFail();
            $this->audit->record(AuthorizationSecurityEvent::ACCOMMODATION_FACILITY_DELETED, $actor->id, 'accommodation_facility', $locked->id, $locked->code, $locked->name, [], $request);
            AccommodationPersistence::save(fn () => $locked->delete());
        });
    }
}
