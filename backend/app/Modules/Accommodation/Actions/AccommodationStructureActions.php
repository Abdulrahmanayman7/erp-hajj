<?php

namespace App\Modules\Accommodation\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Models\User;
use App\Modules\Accommodation\Models\AccommodationFacility;
use App\Modules\Accommodation\Models\AccommodationStructure;
use App\Modules\Accommodation\Support\AccommodationAudit;
use App\Modules\Accommodation\Support\AccommodationHierarchy;
use App\Modules\Accommodation\Support\AccommodationPersistence;
use App\Modules\SeasonPilgrims\Context\SeasonContext;
use App\Modules\SeasonPilgrims\Support\SeasonGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AccommodationStructureActions
{
    public function __construct(private readonly AccommodationAudit $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, AccommodationFacility $facility, array $data, Request $request): AccommodationStructure
    {
        return DB::transaction(function () use ($actor, $facility, $data, $request): AccommodationStructure {
            SeasonGuard::assertWritable(app(SeasonContext::class)->require());
            AccommodationFacility::query()->whereKey($facility->id)->lockForUpdate()->firstOrFail();
            $parentId = isset($data['parent_id']) ? (int) $data['parent_id'] : null;
            AccommodationHierarchy::assertParent((int) $facility->id, $parentId);

            $structure = AccommodationPersistence::save(fn () => AccommodationStructure::query()->create([
                'facility_id' => $facility->id,
                'parent_id' => $parentId,
                'kind' => $data['kind'],
                'name' => trim((string) $data['name']),
                'created_by' => $actor->id,
            ]));

            $this->audit->record(AuthorizationSecurityEvent::ACCOMMODATION_STRUCTURE_CREATED, $actor->id, 'accommodation_structure', $structure->id, null, $structure->name, [
                'after_values' => ['facility_id' => $facility->id, 'parent_id' => $parentId, 'kind' => $structure->kind],
            ], $request);

            return $structure;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, AccommodationStructure $structure, array $data, Request $request): AccommodationStructure
    {
        return DB::transaction(function () use ($actor, $structure, $data, $request): AccommodationStructure {
            SeasonGuard::assertWritable(app(SeasonContext::class)->require());
            $locked = AccommodationStructure::query()->whereKey($structure->id)->lockForUpdate()->firstOrFail();
            AccommodationFacility::query()->whereKey($locked->facility_id)->lockForUpdate()->firstOrFail();
            $parentId = array_key_exists('parent_id', $data) ? ($data['parent_id'] === null ? null : (int) $data['parent_id']) : $locked->parent_id;
            AccommodationHierarchy::assertParent((int) $locked->facility_id, $parentId === null ? null : (int) $parentId, (int) $locked->id);

            $locked->fill([
                'parent_id' => $parentId,
                'kind' => $data['kind'] ?? $locked->kind,
                'name' => array_key_exists('name', $data) ? trim((string) $data['name']) : $locked->name,
            ]);
            AccommodationPersistence::save(fn () => $locked->save());

            $this->audit->record(AuthorizationSecurityEvent::ACCOMMODATION_STRUCTURE_UPDATED, $actor->id, 'accommodation_structure', $locked->id, null, $locked->name, [
                'after_values' => ['parent_id' => $locked->parent_id, 'kind' => $locked->kind, 'name' => $locked->name],
            ], $request);

            return $locked;
        });
    }

    public function delete(User $actor, AccommodationStructure $structure, Request $request): void
    {
        DB::transaction(function () use ($actor, $structure, $request): void {
            SeasonGuard::assertWritable(app(SeasonContext::class)->require());
            $locked = AccommodationStructure::query()->whereKey($structure->id)->lockForUpdate()->firstOrFail();
            $this->audit->record(AuthorizationSecurityEvent::ACCOMMODATION_STRUCTURE_DELETED, $actor->id, 'accommodation_structure', $locked->id, null, $locked->name, [], $request);
            AccommodationPersistence::save(fn () => $locked->delete());
        });
    }
}
