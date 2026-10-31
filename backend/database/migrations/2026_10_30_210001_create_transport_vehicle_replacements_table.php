<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Atomic vehicle replacement executed against an open breakdown (ADR-0021 D03).
 * Reuses T7 history via Append of a new vehicle assignment row (never overwrite).
 *
 * @see docs/09-modules/17-transportation-and-fleet/DATA_MODEL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transport_vehicle_replacements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants', indexName: 'tr_repl_tenant_fk')->restrictOnDelete();
            $table->unsignedBigInteger('season_id');
            $table->unsignedBigInteger('breakdown_id');
            $table->unsignedBigInteger('vehicle_run_id');
            $table->unsignedBigInteger('from_season_vehicle_id');
            $table->unsignedBigInteger('to_season_vehicle_id');
            $table->string('status', 16);
            $table->string('reason', 1000)->nullable();
            $table->unsignedInteger('passengers_retained');
            $table->unsignedInteger('passengers_reassigned');
            $table->string('idempotency_key', 64)->nullable();
            $table->dateTime('executed_at');
            $table->foreignId('executed_by')->constrained('users', indexName: 'tr_repl_executed_by_fk')->restrictOnDelete();
            $table->timestamps();

            $table->foreign(['tenant_id', 'season_id'], 'tr_repl_season_fk')
                ->references(['tenant_id', 'id'])
                ->on('seasons')
                ->restrictOnDelete();
            $table->foreign(['season_id', 'breakdown_id'], 'tr_repl_breakdown_fk')
                ->references(['season_id', 'id'])
                ->on('transport_vehicle_breakdowns')
                ->restrictOnDelete();
            $table->foreign(['season_id', 'vehicle_run_id'], 'tr_repl_run_fk')
                ->references(['season_id', 'id'])
                ->on('transport_vehicle_runs')
                ->restrictOnDelete();
            $table->foreign(['season_id', 'from_season_vehicle_id'], 'tr_repl_from_sv_fk')
                ->references(['season_id', 'id'])
                ->on('transport_season_vehicles')
                ->restrictOnDelete();
            $table->foreign(['season_id', 'to_season_vehicle_id'], 'tr_repl_to_sv_fk')
                ->references(['season_id', 'id'])
                ->on('transport_season_vehicles')
                ->restrictOnDelete();

            $table->unique(['breakdown_id'], 'tr_repl_breakdown_unique');
            $table->unique(['season_id', 'id'], 'tr_repl_season_id_unique');
            $table->unique(['tenant_id', 'idempotency_key'], 'tr_repl_idempotency_unique');
            $table->index(['tenant_id', 'season_id'], 'tr_repl_season_index');
        });

        // Composite FK cannot use ON DELETE SET NULL while season_id is NOT NULL
        // (MySQL errno 150). Replacements are never deleted via API — restrict.
        Schema::table('transport_vehicle_breakdowns', function (Blueprint $table): void {
            $table->foreign(['season_id', 'replacement_id'], 'tr_bd_replacement_fk')
                ->references(['season_id', 'id'])
                ->on('transport_vehicle_replacements')
                ->restrictOnDelete();
        });

        Schema::create('transport_replacement_passenger_moves', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants', indexName: 'tr_rpm_tenant_fk')->restrictOnDelete();
            $table->unsignedBigInteger('season_id');
            $table->unsignedBigInteger('replacement_id');
            $table->unsignedBigInteger('from_run_id');
            $table->unsignedBigInteger('to_run_id');
            $table->unsignedBigInteger('season_pilgrim_id');
            $table->unsignedBigInteger('source_passenger_id');
            $table->unsignedBigInteger('target_passenger_id');
            $table->timestamps();

            $table->foreign(['tenant_id', 'season_id'], 'tr_rpm_season_fk')
                ->references(['tenant_id', 'id'])
                ->on('seasons')
                ->restrictOnDelete();
            $table->foreign(['season_id', 'replacement_id'], 'tr_rpm_replacement_fk')
                ->references(['season_id', 'id'])
                ->on('transport_vehicle_replacements')
                ->restrictOnDelete();
            $table->foreign(['season_id', 'from_run_id'], 'tr_rpm_from_run_fk')
                ->references(['season_id', 'id'])
                ->on('transport_vehicle_runs')
                ->restrictOnDelete();
            $table->foreign(['season_id', 'to_run_id'], 'tr_rpm_to_run_fk')
                ->references(['season_id', 'id'])
                ->on('transport_vehicle_runs')
                ->restrictOnDelete();
            $table->foreign(['tenant_id', 'season_id', 'season_pilgrim_id'], 'tr_rpm_season_pilgrim_fk')
                ->references(['tenant_id', 'season_id', 'id'])
                ->on('season_pilgrims')
                ->restrictOnDelete();

            $table->unique(['replacement_id', 'source_passenger_id'], 'tr_rpm_source_unique');
            $table->index(['replacement_id'], 'tr_rpm_replacement_index');
        });
    }

    public function down(): void
    {
        Schema::table('transport_vehicle_breakdowns', function (Blueprint $table): void {
            $table->dropForeign('tr_bd_replacement_fk');
        });
        Schema::dropIfExists('transport_replacement_passenger_moves');
        Schema::dropIfExists('transport_vehicle_replacements');
    }
};
