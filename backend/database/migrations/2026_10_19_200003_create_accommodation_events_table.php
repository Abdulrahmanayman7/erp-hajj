<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 3 Gate B: append-only operational timeline foundation.
 * No event-producing actions in this gate.
 *
 * @see docs/reviews/ACCOMMODATION_SPRINT3_SCOPED_APPROVAL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accommodation_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants', indexName: 'acc_evt_tenant_fk')->restrictOnDelete();
            $table->unsignedBigInteger('season_id');
            $table->unsignedBigInteger('assignment_id');
            $table->string('event_type', 32);
            $table->dateTime('occurred_at');
            $table->dateTime('recorded_at');
            $table->foreignId('actor_user_id')->nullable()->constrained('users', indexName: 'acc_evt_actor_fk')->restrictOnDelete();
            $table->string('source', 64);
            $table->string('idempotency_key', 191);
            $table->string('correlation_id', 100)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['tenant_id', 'id'], 'acc_evt_tenant_id_uq');
            $table->unique(['tenant_id', 'idempotency_key'], 'acc_evt_tenant_idem_uq');
            $table->index(['tenant_id', 'season_id', 'assignment_id', 'id'], 'acc_evt_assignment_idx');

            $table->foreign(['tenant_id', 'season_id'], 'acc_evt_season_fk')
                ->references(['tenant_id', 'id'])
                ->on('seasons')
                ->restrictOnDelete();
            $table->foreign(['tenant_id', 'season_id', 'assignment_id'], 'acc_evt_assignment_fk')
                ->references(['tenant_id', 'season_id', 'id'])
                ->on('accommodation_assignments')
                ->restrictOnDelete();
        });

        $driver = Schema::getConnection()->getDriverName();
        $eventTypeCheck = "event_type in ('arrived','checked_in','checked_out','relocated','cancelled')";

        if ($driver === 'mysql') {
            DB::statement("alter table accommodation_events add constraint acc_evt_type_ck check ({$eventTypeCheck})");
        } elseif ($driver === 'sqlite') {
            // SQLite: rebuild with CHECK (create already ran without it).
            $this->attachSqliteEventTypeCheck($eventTypeCheck);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('accommodation_events');
    }

    private function attachSqliteEventTypeCheck(string $expression): void
    {
        $rows = DB::table('accommodation_events')->get()->map(fn ($row) => (array) $row)->all();
        $master = DB::select(
            'select type, sql from sqlite_master where tbl_name = ? and sql is not null',
            ['accommodation_events'],
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
            throw new RuntimeException('Cannot attach accommodation event type CHECK on SQLite.');
        }
        $tableSql = substr($tableSql, 0, -1).", constraint acc_evt_type_ck check ({$expression}))";
        DB::statement('drop table accommodation_events');
        DB::statement($tableSql);
        foreach ($dependents as $sql) {
            DB::statement($sql);
        }
        foreach ($rows as $row) {
            DB::table('accommodation_events')->insert($row);
        }
    }
};
