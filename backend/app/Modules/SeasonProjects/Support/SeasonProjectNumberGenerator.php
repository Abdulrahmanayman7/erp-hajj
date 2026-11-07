<?php

namespace App\Modules\SeasonProjects\Support;

use App\Core\Tenancy\TenantContext;
use App\Modules\SeasonProjects\Exceptions\SeasonProjectDomainException;
use Illuminate\Support\Facades\DB;
use Throwable;

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
            $now = now()->format('Y-m-d H:i:s');

            $row = DB::table('season_project_number_sequences')
                ->where('tenant_id', $tenant->id)
                ->where('season_id', $seasonId)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                try {
                    DB::table('season_project_number_sequences')->insert([
                        'tenant_id' => $tenant->id,
                        'season_id' => $seasonId,
                        'next_value' => 2,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $value = 1;
                } catch (Throwable) {
                    $row = DB::table('season_project_number_sequences')
                        ->where('tenant_id', $tenant->id)
                        ->where('season_id', $seasonId)
                        ->lockForUpdate()
                        ->first();

                    if ($row === null) {
                        throw SeasonProjectDomainException::numberConflict();
                    }

                    $value = (int) $row->next_value;
                    DB::table('season_project_number_sequences')
                        ->where('tenant_id', $tenant->id)
                        ->where('season_id', $seasonId)
                        ->update([
                            'next_value' => $value + 1,
                            'updated_at' => $now,
                        ]);
                }
            } else {
                $value = (int) $row->next_value;
                DB::table('season_project_number_sequences')
                    ->where('tenant_id', $tenant->id)
                    ->where('season_id', $seasonId)
                    ->update([
                        'next_value' => $value + 1,
                        'updated_at' => $now,
                    ]);
            }

            if ($value < 1) {
                throw SeasonProjectDomainException::numberConflict();
            }

            return $prefix.str_pad((string) $value, $pad, '0', STR_PAD_LEFT);
        };

        if (DB::transactionLevel() > 0) {
            return $allocate();
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
}
