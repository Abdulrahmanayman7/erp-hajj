<?php

namespace App\Modules\SeasonProjects\Support;

use App\Core\Tenancy\TenantContext;
use App\Modules\SeasonProjects\Exceptions\SeasonProjectDomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Per tenant+season SPJ-###### allocator.
 *
 * MySQL/MariaDB: atomic INSERT … ON DUPLICATE KEY UPDATE + LAST_INSERT_ID.
 * The prior SELECT-miss → INSERT → catch(Throwable) path could swallow engine
 * deadlocks (MySQL rolls back the whole transaction); the outer Laravel commit
 * then raised PDO "There is no active transaction" under multi-season races.
 *
 * SQLite (Feature CI): lockForUpdate increment with a narrow unique-conflict
 * catch only — SQLite has no ON DUPLICATE KEY / LAST_INSERT_ID.
 */
final class SeasonProjectNumberGenerator
{
    private const TRANSACTION_ATTEMPTS = 5;

    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function next(int $seasonId): string
    {
        $tenant = $this->tenantContext->require();
        $prefix = (string) config('season_projects.number_prefix', 'SPJ-');
        $pad = max(1, (int) config('season_projects.number_pad', 6));

        $allocate = function () use ($tenant, $seasonId, $prefix, $pad): string {
            $driver = DB::connection()->getDriverName();
            $value = in_array($driver, ['mysql', 'mariadb'], true)
                ? $this->allocateAtomicMySql((int) $tenant->id, $seasonId)
                : $this->allocateLockedPortable((int) $tenant->id, $seasonId);

            if ($value < 1) {
                throw SeasonProjectDomainException::numberConflict();
            }

            return $prefix.str_pad((string) $value, $pad, '0', STR_PAD_LEFT);
        };

        if (DB::transactionLevel() > 0) {
            try {
                return $allocate();
            } catch (Throwable $e) {
                if ($e instanceof SeasonProjectDomainException) {
                    throw $e;
                }

                throw $e;
            }
        }

        try {
            return DB::transaction($allocate, self::TRANSACTION_ATTEMPTS);
        } catch (Throwable $e) {
            if ($e instanceof SeasonProjectDomainException) {
                throw $e;
            }

            throw SeasonProjectDomainException::numberConflict();
        }
    }

    private function allocateAtomicMySql(int $tenantId, int $seasonId): int
    {
        $now = now()->format('Y-m-d H:i:s');

        DB::affectingStatement(
            'INSERT INTO season_project_number_sequences
                (tenant_id, season_id, next_value, created_at, updated_at)
             VALUES (?, ?, LAST_INSERT_ID(1) + 1, ?, ?)
             ON DUPLICATE KEY UPDATE
                next_value = LAST_INSERT_ID(next_value) + 1,
                updated_at = VALUES(updated_at)',
            [$tenantId, $seasonId, $now, $now],
        );

        return (int) (DB::selectOne('SELECT LAST_INSERT_ID() AS v')->v ?? 0);
    }

    private function allocateLockedPortable(int $tenantId, int $seasonId): int
    {
        $now = now()->format('Y-m-d H:i:s');

        $row = DB::table('season_project_number_sequences')
            ->where('tenant_id', $tenantId)
            ->where('season_id', $seasonId)
            ->lockForUpdate()
            ->first();

        if ($row === null) {
            try {
                DB::table('season_project_number_sequences')->insert([
                    'tenant_id' => $tenantId,
                    'season_id' => $seasonId,
                    'next_value' => 2,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                return 1;
            } catch (QueryException $e) {
                if (! $this->isUniqueConflict($e)) {
                    throw $e;
                }

                $row = DB::table('season_project_number_sequences')
                    ->where('tenant_id', $tenantId)
                    ->where('season_id', $seasonId)
                    ->lockForUpdate()
                    ->first();

                if ($row === null) {
                    throw SeasonProjectDomainException::numberConflict();
                }
            }
        }

        $value = (int) $row->next_value;
        DB::table('season_project_number_sequences')
            ->where('tenant_id', $tenantId)
            ->where('season_id', $seasonId)
            ->update([
                'next_value' => $value + 1,
                'updated_at' => $now,
            ]);

        return $value;
    }

    private function isUniqueConflict(QueryException $e): bool
    {
        $message = $e->getMessage();

        return $e->getCode() === '23000'
            || str_contains($message, 'UNIQUE constraint failed')
            || str_contains($message, 'Duplicate entry');
    }
}
