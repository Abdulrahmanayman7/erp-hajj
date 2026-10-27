<?php

namespace App\Modules\Accommodation\Support;

use App\Core\Tenancy\TenantContext;
use App\Modules\Accommodation\Models\AccommodationAssignment;
use App\Modules\Accommodation\Models\AccommodationPilgrimLock;
use App\Modules\Accommodation\Models\AccommodationSeasonPosition;
use App\Modules\Accommodation\Models\AccommodationSeasonUnit;
use App\Modules\Accommodation\Models\AccommodationUnit;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

/**
 * Deterministic InnoDB lock order for future confirmation (Gate B foundation).
 *
 * Order: stable units → pilgrim lock anchors → season units → season positions → assignments.
 * Never lock season_pilgrims for occupancy serialization.
 *
 * Concurrent anchor creation: insert the unique row; on duplicate-key race, ignore
 * and SELECT … FOR UPDATE the committed row. Both connections serialize on the
 * unique index / row lock without double-inserting.
 */
final class AccommodationLockOrder
{
    /**
     * @param  list<int>  $unitIds
     * @return Collection<int, AccommodationUnit>
     */
    public function lockUnits(array $unitIds): Collection
    {
        $ids = $this->uniqueSortedIds($unitIds);
        if ($ids === []) {
            return collect();
        }

        return AccommodationUnit::query()
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
    }

    /**
     * Ensure lock-anchor rows exist, then lock them by season_pilgrim_id order.
     *
     * @param  list<int>  $seasonPilgrimIds
     * @return Collection<int, AccommodationPilgrimLock>
     */
    public function ensureAndLockPilgrimAnchors(int $seasonId, array $seasonPilgrimIds): Collection
    {
        $ids = $this->uniqueSortedIds($seasonPilgrimIds);
        if ($ids === []) {
            return collect();
        }

        $tenantId = app(TenantContext::class)->require()->id;
        $now = now();
        $rows = array_map(fn (int $id): array => [
            'tenant_id' => $tenantId, 'season_id' => $seasonId, 'season_pilgrim_id' => $id,
            'created_at' => $now, 'updated_at' => $now,
        ], $ids);
        // Unlike insertOrIgnore this does not suppress FK / invalid-data errors.
        // Sorted input acquires the same per-pilgrim unique-key locks in order.
        DB::table('accommodation_pilgrim_locks')->upsert(
            $rows, ['tenant_id', 'season_id', 'season_pilgrim_id'], ['season_pilgrim_id'],
        );

        return AccommodationPilgrimLock::query()
            ->where('season_id', $seasonId)
            ->whereIn('season_pilgrim_id', $ids)
            ->orderBy('season_pilgrim_id')
            ->lockForUpdate()
            ->get()
            ->keyBy('season_pilgrim_id');
    }

    /**
     * @param  list<int>  $seasonUnitIds
     * @return Collection<int, AccommodationSeasonUnit>
     */
    public function lockSeasonUnits(array $seasonUnitIds): Collection
    {
        $ids = $this->uniqueSortedIds($seasonUnitIds);
        if ($ids === []) {
            return collect();
        }

        return AccommodationSeasonUnit::query()
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
    }

    /**
     * @param  list<int>  $seasonPositionIds
     * @return Collection<int, AccommodationSeasonPosition>
     */
    public function lockSeasonPositions(array $seasonPositionIds): Collection
    {
        $ids = $this->uniqueSortedIds($seasonPositionIds);
        if ($ids === []) {
            return collect();
        }

        return AccommodationSeasonPosition::query()
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
    }

    /**
     * @param  list<int>  $assignmentIds
     * @return Collection<int, AccommodationAssignment>
     */
    public function lockAssignments(array $assignmentIds): Collection
    {
        $ids = $this->uniqueSortedIds($assignmentIds);
        if ($ids === []) {
            return collect();
        }

        return AccommodationAssignment::query()
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
    }

    public function ensurePilgrimAnchor(int $seasonId, int $seasonPilgrimId): void
    {
        $exists = AccommodationPilgrimLock::query()
            ->where('season_id', $seasonId)
            ->where('season_pilgrim_id', $seasonPilgrimId)
            ->exists();

        if ($exists) {
            return;
        }

        try {
            AccommodationPilgrimLock::query()->create([
                'season_id' => $seasonId,
                'season_pilgrim_id' => $seasonPilgrimId,
            ]);
        } catch (QueryException $e) {
            if (! $this->isUniqueViolation($e)) {
                throw $e;
            }
        }
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        $sqlState = (string) ($e->errorInfo[0] ?? '');
        $driverCode = (int) ($e->errorInfo[1] ?? 0);

        // MySQL/MariaDB ER_DUP_ENTRY = 1062; SQLSTATE 23000.
        return $sqlState === '23000' || $driverCode === 1062 || str_contains($e->getMessage(), 'UNIQUE');
    }

    /**
     * @param  list<int|string|null>  $ids
     * @return list<int>
     */
    private function uniqueSortedIds(array $ids): array
    {
        $normalized = [];
        foreach ($ids as $id) {
            $value = (int) $id;
            if ($value > 0) {
                $normalized[$value] = $value;
            }
        }

        $list = array_values($normalized);
        sort($list);

        return $list;
    }
}
