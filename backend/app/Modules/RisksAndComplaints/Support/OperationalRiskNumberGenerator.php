<?php

namespace App\Modules\RisksAndComplaints\Support;

use App\Core\Tenancy\TenantContext;
use App\Modules\RisksAndComplaints\Exceptions\RisksAndComplaintsDomainException;
use App\Modules\SeasonPilgrims\Models\Season;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Allocates RSK-YYYY-###### per tenant + season + year (S07-D06).
 * Sequence only advances — soft-deleted numbers are never reused.
 *
 * MySQL: atomic LAST_INSERT_ID upsert. SQLite Feature CI: locked portable path
 * with a narrow unique-conflict catch (never swallow deadlocks).
 */
final class OperationalRiskNumberGenerator
{
    private const TABLE = 'operational_risk_number_sequences';

    private const TRANSACTION_ATTEMPTS = 5;

    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function next(Season $season, ?int $year = null): string
    {
        $tenant = $this->tenantContext->require();
        $year ??= (int) now()->year;
        $prefix = (string) config('risks_complaints.risk_number_prefix', 'RSK-');
        $pad = max(1, (int) config('risks_complaints.number_pad', 6));

        $allocate = function () use ($tenant, $season, $year, $prefix, $pad): string {
            $driver = DB::connection()->getDriverName();
            $value = in_array($driver, ['mysql', 'mariadb'], true)
                ? $this->allocateAtomicMySql((int) $tenant->id, (int) $season->id, $year)
                : $this->allocateLockedPortable((int) $tenant->id, (int) $season->id, $year);

            if ($value < 1) {
                throw RisksAndComplaintsDomainException::riskNumberUnavailable();
            }

            return $prefix.$year.'-'.str_pad((string) $value, $pad, '0', STR_PAD_LEFT);
        };

        if (DB::transactionLevel() > 0) {
            return $allocate();
        }

        try {
            return DB::transaction($allocate, self::TRANSACTION_ATTEMPTS);
        } catch (Throwable $e) {
            if ($e instanceof RisksAndComplaintsDomainException) {
                throw $e;
            }

            throw RisksAndComplaintsDomainException::riskNumberUnavailable();
        }
    }

    private function allocateAtomicMySql(int $tenantId, int $seasonId, int $year): int
    {
        $now = now()->format('Y-m-d H:i:s');

        DB::affectingStatement(
            'INSERT INTO '.self::TABLE.'
                (tenant_id, season_id, year, next_number, created_at, updated_at)
             VALUES (?, ?, ?, LAST_INSERT_ID(1) + 1, ?, ?)
             ON DUPLICATE KEY UPDATE
                next_number = LAST_INSERT_ID(next_number) + 1,
                updated_at = VALUES(updated_at)',
            [$tenantId, $seasonId, $year, $now, $now],
        );

        return (int) (DB::selectOne('SELECT LAST_INSERT_ID() AS v')->v ?? 0);
    }

    private function allocateLockedPortable(int $tenantId, int $seasonId, int $year): int
    {
        $keys = [
            'tenant_id' => $tenantId,
            'season_id' => $seasonId,
            'year' => $year,
        ];
        $now = now();

        $row = DB::table(self::TABLE)->where($keys)->lockForUpdate()->first();

        if ($row === null) {
            try {
                DB::table(self::TABLE)->insert([
                    ...$keys,
                    'next_number' => 2,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                return 1;
            } catch (QueryException $e) {
                if (! $this->isUniqueConflict($e)) {
                    throw $e;
                }

                $row = DB::table(self::TABLE)->where($keys)->lockForUpdate()->first();
                if ($row === null) {
                    throw RisksAndComplaintsDomainException::riskNumberUnavailable();
                }
            }
        }

        $value = (int) $row->next_number;
        DB::table(self::TABLE)->where($keys)->update([
            'next_number' => $value + 1,
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
