<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 3 Gate B: widen acc_asg_booking_ck for confirmed ops, cancelled,
 * and completed checkout states. Preserves draft and Sprint 2 confirmed rows.
 *
 * @see docs/reviews/ACCOMMODATION_SPRINT3_SCOPED_APPROVAL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->replaceMysqlOrSqliteCheck(
            dropName: 'acc_asg_booking_ck',
            addName: 'acc_asg_booking_ck',
            expression: $this->sprint3BookingCheckExpression(),
        );
    }

    public function down(): void
    {
        $this->replaceMysqlOrSqliteCheck(
            dropName: 'acc_asg_booking_ck',
            addName: 'acc_asg_booking_ck',
            expression: $this->sprint2BookingCheckExpression(),
        );
    }

    private function sprint3BookingCheckExpression(): string
    {
        $draft = "booking_status = 'draft'"
            .' and operational_status is null'
            .' and family_group_id is null'
            .' and checkout_source is null'
            .' and actual_departed_at is null'
            .' and relocated_from_assignment_id is null'
            .' and effective_ends_at = original_ends_at'
            .' and original_ends_at > starts_at';

        $confirmed = "booking_status = 'confirmed'"
            ." and (operational_status is null or operational_status in ('assigned','arrived','checked_in'))"
            .' and checkout_source is null'
            .' and actual_departed_at is null'
            .' and effective_ends_at <= original_ends_at'
            .' and effective_ends_at > starts_at';

        $cancelled = "booking_status = 'cancelled'"
            ." and (operational_status is null or operational_status in ('assigned','arrived'))"
            .' and checkout_source is null'
            .' and actual_departed_at is null'
            .' and effective_ends_at <= original_ends_at'
            .' and effective_ends_at > starts_at';

        // Require non-null ops/source: SQL CHECK treats "col = 'x'" as UNKNOWN when
        // col IS NULL, which would incorrectly accept the row.
        $completed = "booking_status = 'completed'"
            ." and operational_status is not null and operational_status = 'checked_out'"
            ." and checkout_source is not null and checkout_source = 'staff'"
            .' and effective_ends_at <= original_ends_at'
            .' and effective_ends_at > starts_at';

        // Do NOT compare relocated_from_assignment_id to id here: MariaDB 10.4
        // rejects AUTO_INCREMENT columns in CHECK (error 1901). Self-reference
        // is rejected by Gate C writers (scoped approval: "where supported").
        return "({$draft}) or ({$confirmed}) or ({$cancelled}) or ({$completed})";
    }

    private function sprint2BookingCheckExpression(): string
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
     * Rebuild assignments with Sprint 3 columns + CHECK (SQLite cannot ALTER CHECK).
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
            $table->dateTime('actual_departed_at')->nullable();
            $table->unsignedBigInteger('family_group_id')->nullable();
            $table->string('checkout_source', 32)->nullable();
            $table->unsignedBigInteger('relocated_from_assignment_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'acc_asg_created_by_fk')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'id'], 'acc_asg_tenant_id_uq');
            $table->unique(['tenant_id', 'season_id', 'id'], 'acc_asg_tenant_season_id_uq');
            $table->index(['tenant_id', 'season_id', 'season_unit_id', 'starts_at'], 'acc_asg_unit_start_idx');
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
            $table->index(
                ['tenant_id', 'season_id', 'relocated_from_assignment_id'],
                'acc_asg_relocated_from_idx',
            );

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
            $table->foreign(
                ['tenant_id', 'season_id', 'relocated_from_assignment_id'],
                'acc_asg_relocated_from_fk',
            )
                ->references(['tenant_id', 'season_id', 'id'])
                ->on('accommodation_assignments')
                ->restrictOnDelete();
        });

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
            throw new RuntimeException('Cannot attach Sprint 3 accommodation booking CHECK on SQLite.');
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
