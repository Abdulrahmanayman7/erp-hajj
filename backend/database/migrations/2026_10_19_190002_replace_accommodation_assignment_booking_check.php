<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gate B: replace draft-only acc_asg_draft_ck with status-aware acc_asg_booking_ck
 * (draft | confirmed). Adds overlap-query indexes. No confirmation side effects.
 *
 * @see docs/reviews/ACCOMMODATION_SPRINT2_SCOPED_APPROVAL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->replaceMysqlOrSqliteCheck(
            dropName: 'acc_asg_draft_ck',
            addName: 'acc_asg_booking_ck',
            expression: $this->bookingCheckExpression(),
        );

        // Lead with booking_status so InnoDB does not adopt these indexes as the
        // supporting index for existing composite FKs (blocks DROP INDEX on rollback).
        Schema::table('accommodation_assignments', function (Blueprint $table): void {
            $table->index(
                ['booking_status', 'tenant_id', 'season_id', 'season_pilgrim_id', 'starts_at'],
                'acc_asg_pilgrim_status_start_idx',
            );
            $table->index(
                ['booking_status', 'tenant_id', 'unit_id', 'starts_at'],
                'acc_asg_unit_status_start_idx',
            );
            $table->index(
                ['booking_status', 'tenant_id', 'season_id', 'season_position_id', 'starts_at'],
                'acc_asg_position_status_start_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('accommodation_assignments', function (Blueprint $table): void {
            $table->dropIndex('acc_asg_pilgrim_status_start_idx');
            $table->dropIndex('acc_asg_unit_status_start_idx');
            $table->dropIndex('acc_asg_position_status_start_idx');
        });

        $this->replaceMysqlOrSqliteCheck(
            dropName: 'acc_asg_booking_ck',
            addName: 'acc_asg_draft_ck',
            expression: "booking_status = 'draft'"
                .' and operational_status is null'
                .' and family_group_id is null'
                .' and checkout_source is null'
                .' and effective_ends_at = original_ends_at'
                .' and original_ends_at > starts_at',
        );
    }

    private function bookingCheckExpression(): string
    {
        $draft = "booking_status = 'draft'"
            .' and operational_status is null'
            .' and family_group_id is null'
            .' and checkout_source is null'
            .' and effective_ends_at = original_ends_at'
            .' and original_ends_at > starts_at';

        $confirmed = "booking_status = 'confirmed'"
            .' and operational_status is null'
            .' and checkout_source is null'
            .' and effective_ends_at = original_ends_at'
            .' and original_ends_at > starts_at';

        return "({$draft}) or ({$confirmed})";
    }

    private function replaceMysqlOrSqliteCheck(string $dropName, string $addName, string $expression): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            $this->dropMysqlCheck($dropName);
            DB::statement("alter table accommodation_assignments add constraint {$addName} check ({$expression})");

            return;
        }

        if ($driver !== 'sqlite') {
            throw new RuntimeException('Accommodation booking CHECK replacement is not implemented for '.$driver);
        }

        $this->rebuildSqliteWithCheck($addName, $expression);
    }

    private function dropMysqlCheck(string $name): void
    {
        try {
            DB::statement("alter table accommodation_assignments drop check {$name}");
        } catch (\Throwable) {
            DB::statement("alter table accommodation_assignments drop constraint {$name}");
        }
    }

    /**
     * Copy rows, drop table, recreate columns/FKs/indexes from Sprint 1 shape, attach CHECK.
     */
    private function rebuildSqliteWithCheck(string $constraintName, string $expression): void
    {
        $rows = DB::table('accommodation_assignments')->get()->map(fn ($row) => (array) $row)->all();

        Schema::drop('accommodation_assignments');

        Schema::create('accommodation_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants', indexName: 'acc_asg_tenant_fk')->restrictOnDelete();
            $table->unsignedBigInteger('season_id');
            $table->unsignedBigInteger('season_pilgrim_id');
            $table->unsignedBigInteger('season_unit_id');
            $table->unsignedBigInteger('unit_id');
            $table->unsignedBigInteger('season_position_id')->nullable();
            $table->string('booking_status', 32);
            $table->string('operational_status', 32)->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('original_ends_at');
            $table->dateTime('effective_ends_at');
            $table->unsignedBigInteger('family_group_id')->nullable();
            $table->string('checkout_source', 32)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'acc_asg_created_by_fk')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'id'], 'acc_asg_tenant_id_uq');
            $table->unique(['tenant_id', 'season_id', 'id'], 'acc_asg_tenant_season_id_uq');
            $table->index(['tenant_id', 'season_id', 'season_unit_id', 'starts_at'], 'acc_asg_unit_start_idx');

            $table->foreign(['tenant_id', 'season_id'], 'acc_asg_season_fk')
                ->references(['tenant_id', 'id'])
                ->on('seasons')
                ->restrictOnDelete();
            $table->foreign(['tenant_id', 'season_id', 'season_pilgrim_id'], 'acc_asg_pilgrim_fk')
                ->references(['tenant_id', 'season_id', 'id'])
                ->on('season_pilgrims')
                ->restrictOnDelete();
            $table->foreign(['tenant_id', 'season_id', 'season_unit_id', 'unit_id'], 'acc_asg_unit_fk')
                ->references(['tenant_id', 'season_id', 'id', 'unit_id'])
                ->on('accommodation_season_units')
                ->restrictOnDelete();
            $table->foreign(['tenant_id', 'season_id', 'season_position_id', 'season_unit_id'], 'acc_asg_position_fk')
                ->references(['tenant_id', 'season_id', 'id', 'season_unit_id'])
                ->on('accommodation_season_positions')
                ->restrictOnDelete();
        });

        // SQLite rejects ALTER TABLE … ADD CONSTRAINT CHECK; rebuild empty table like Sprint 1.
        $master = DB::select(
            'select type, sql from sqlite_master where tbl_name = ? and sql is not null',
            ['accommodation_assignments'],
        );
        $tableSql = null;
        $dependents = [];
        foreach ($master as $row) {
            if ($row->type === 'table') {
                $tableSql = $row->sql;
            } else {
                $dependents[] = $row->sql;
            }
        }
        $tableSql = is_string($tableSql) ? rtrim($tableSql) : '';
        if ($tableSql === '' || ! str_ends_with($tableSql, ')')) {
            throw new RuntimeException('Cannot attach accommodation booking CHECK on SQLite.');
        }
        $tableSql = substr($tableSql, 0, -1).", constraint {$constraintName} check ({$expression}))";
        DB::statement('drop table accommodation_assignments');
        DB::statement($tableSql);
        foreach ($dependents as $sql) {
            DB::statement($sql);
        }

        foreach ($rows as $row) {
            DB::table('accommodation_assignments')->insert($row);
        }
    }
};
