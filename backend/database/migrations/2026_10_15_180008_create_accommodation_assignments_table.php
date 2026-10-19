<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Draft stays only. Overlapping drafts are allowed. capacity is not referenced.
 *
 * Blueprint has no CHECK clause. SQLite does not enforce NOT NULL on a generated
 * column, so the draft rule is a table CHECK named acc_asg_draft_ck. MySQL adds
 * it with ALTER TABLE. SQLite requires it inside CREATE TABLE, so the empty table
 * is rebuilt from sqlite_master before any rows exist.
 *
 * The position foreign key includes season_unit_id so a set position must belong
 * to that season unit. A NULL position skips the check (MATCH SIMPLE).
 *
 * @see docs/09-modules/18-accommodation-and-camps/DATA_MODEL.md
 */
return new class extends Migration
{
    public function up(): void
    {
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
            // DATETIME, not TIMESTAMP. MariaDB 10.4 allows only one NOT NULL
            // TIMESTAMP before it invents a zero-date default, which strict mode rejects.
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

        $this->addDraftOnlyCheck();
    }

    /**
     * Rejects every row that is not a non-reserving draft with a half-open interval.
     */
    private function addDraftOnlyCheck(): void
    {
        $expression = "booking_status = 'draft'"
            .' and operational_status is null'
            .' and family_group_id is null'
            .' and checkout_source is null'
            .' and effective_ends_at = original_ends_at'
            .' and original_ends_at > starts_at';

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("alter table accommodation_assignments add constraint acc_asg_draft_ck check ({$expression})");

            return;
        }

        if ($driver !== 'sqlite') {
            throw new RuntimeException('The accommodation draft check is not implemented for the '.$driver.' driver.');
        }

        $rows = DB::select(
            'select type, sql from sqlite_master where tbl_name = ? and sql is not null',
            ['accommodation_assignments'],
        );

        $tableSql = null;
        $dependents = [];

        foreach ($rows as $row) {
            if ($row->type === 'table') {
                $tableSql = $row->sql;
            } else {
                $dependents[] = $row->sql;
            }
        }

        $tableSql = is_string($tableSql) ? rtrim($tableSql) : '';

        if ($tableSql === '' || ! str_ends_with($tableSql, ')')) {
            throw new RuntimeException('Cannot attach the accommodation draft check.');
        }

        $tableSql = substr($tableSql, 0, -1).", constraint acc_asg_draft_ck check ({$expression}))";

        DB::statement('drop table accommodation_assignments');
        DB::statement($tableSql);

        foreach ($dependents as $sql) {
            DB::statement($sql);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('accommodation_assignments');
    }
};
