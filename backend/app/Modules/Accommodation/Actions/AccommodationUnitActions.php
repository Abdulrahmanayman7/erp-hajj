<?php

namespace App\Modules\Accommodation\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Models\User;
use App\Modules\Accommodation\Exceptions\AccommodationDomainException;
use App\Modules\Accommodation\Models\AccommodationFacility;
use App\Modules\Accommodation\Models\AccommodationStructure;
use App\Modules\Accommodation\Models\AccommodationUnit;
use App\Modules\Accommodation\Support\AccommodationAudit;
use App\Modules\Accommodation\Support\AccommodationCodeGenerator;
use App\Modules\Accommodation\Support\AccommodationPersistence;
use App\Modules\SeasonPilgrims\Context\SeasonContext;
use App\Modules\SeasonPilgrims\Support\SeasonGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AccommodationUnitActions
{
    public function __construct(private readonly AccommodationAudit $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, AccommodationFacility $facility, array $data, Request $request): AccommodationUnit
    {
        return DB::transaction(function () use ($actor, $facility, $data, $request): AccommodationUnit {
            SeasonGuard::assertWritable(app(SeasonContext::class)->require());
            $structureId = isset($data['structure_id']) ? (int) $data['structure_id'] : null;
            $this->assertStructure($facility, $structureId);

            $unit = AccommodationPersistence::save(fn () => AccommodationUnit::query()->create([
                'facility_id' => $facility->id,
                'structure_id' => $structureId,
                'unit_kind' => $data['unit_kind'],
                'code' => isset($data['code']) && trim((string) $data['code']) !== ''
                    ? trim((string) $data['code'])
                    : app(AccommodationCodeGenerator::class)->next($data['unit_kind']),
                'created_by' => $actor->id,
            ]));

            $this->audit->record(AuthorizationSecurityEvent::ACCOMMODATION_UNIT_CREATED, $actor->id, 'accommodation_unit', $unit->id, $unit->code, null, [
                'after_values' => ['facility_id' => $facility->id, 'unit_kind' => $unit->unit_kind, 'code' => $unit->code],
            ], $request);

            return $unit;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, AccommodationUnit $unit, array $data, Request $request): AccommodationUnit
    {
        return DB::transaction(function () use ($actor, $unit, $data, $request): AccommodationUnit {
            SeasonGuard::assertWritable(app(SeasonContext::class)->require());
            $locked = AccommodationUnit::query()->whereKey($unit->id)->lockForUpdate()->firstOrFail();
            $facility = AccommodationFacility::query()->findOrFail($locked->facility_id);
            $structureId = array_key_exists('structure_id', $data)
                ? ($data['structure_id'] === null ? null : (int) $data['structure_id'])
                : $locked->structure_id;
            $this->assertStructure($facility, $structureId === null ? null : (int) $structureId);

            $locked->fill([
                'structure_id' => $structureId,
                'unit_kind' => $data['unit_kind'] ?? $locked->unit_kind,
                'code' => array_key_exists('code', $data) ? trim((string) $data['code']) : $locked->code,
            ]);
            AccommodationPersistence::save(fn () => $locked->save());

            $this->audit->record(AuthorizationSecurityEvent::ACCOMMODATION_UNIT_UPDATED, $actor->id, 'accommodation_unit', $locked->id, $locked->code, null, [
                'after_values' => ['structure_id' => $locked->structure_id, 'unit_kind' => $locked->unit_kind, 'code' => $locked->code],
            ], $request);

            return $locked;
        });
    }

    public function delete(User $actor, AccommodationUnit $unit, Request $request): void
    {
        DB::transaction(function () use ($actor, $unit, $request): void {
            SeasonGuard::assertWritable(app(SeasonContext::class)->require());
            $locked = AccommodationUnit::query()->whereKey($unit->id)->lockForUpdate()->firstOrFail();
            $this->audit->record(AuthorizationSecurityEvent::ACCOMMODATION_UNIT_DELETED, $actor->id, 'accommodation_unit', $locked->id, $locked->code, null, [], $request);
            AccommodationPersistence::save(fn () => $locked->delete());
        });
    }

    private function assertStructure(AccommodationFacility $facility, ?int $structureId): void
    {
        if ($structureId === null) {
            return;
        }

        $exists = AccommodationStructure::query()
            ->whereKey($structureId)
            ->where('facility_id', $facility->id)
            ->exists();

        if (! $exists) {
            throw AccommodationDomainException::invalidReference();
        }
    }
}
