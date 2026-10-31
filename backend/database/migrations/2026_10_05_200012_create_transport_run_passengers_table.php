<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Materialized manifest: one row per SeasonPilgrim per Run (ADR-0018
 * T5/T8/T9).
 *
 * - (tenant_id, season_id, season_pilgrim_id) → season_pilgrims: same tenant
 *   and season, RESTRICT (history is never deleted).
 * - `occupying_flag` (1 for assigned/boarded/arrived) + UNIQUE
 *   (vehicle_run_id, season_pilgrim_id, occupying_flag) → no duplicate
 *   occupying passenger; cancelled/no-show rows stay as history.
 * - Source group and Program / entitlement / vehicle classification columns
 *   are immutable assignment-time snapshots with no FK (T9 / T10). Live
 *   entitlement_override* may change later without rewriting the snapshot.
 *
 * @see docs/09-modules/17-transportation-and-fleet/DATA_MODEL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transport_run_passengers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants', indexName: 'tr_passengers_tenant_fk')->restrictOnDelete();
            $table->unsignedBigInteger('season_id');
            $table->unsignedBigInteger('vehicle_run_id');
            $table->unsignedBigInteger('season_pilgrim_id');
            $table->string('status', 32);
            $table->unsignedTinyInteger('occupying_flag')
                ->nullable()
                ->storedAs("case when status in ('assigned', 'boarded', 'arrived') then 1 else null end");
            $table->string('assignment_source', 32);
            $table->unsignedBigInteger('source_group_id')->nullable();
            $table->string('source_group_label', 255)->nullable();
            $table->unsignedBigInteger('program_id_at_assignment')->nullable();
            $table->string('program_code_at_assignment', 40)->nullable();
            $table->unsignedBigInteger('entitlement_id_at_assignment')->nullable();
            $table->string('entitlement_vehicle_class', 32)->nullable();
            $table->string('entitlement_service_level', 32)->nullable();
            $table->unsignedBigInteger('season_vehicle_id_at_assignment')->nullable();
            $table->string('vehicle_class_at_assignment', 32)->nullable();
            $table->string('service_level_at_assignment', 32)->nullable();
            $table->string('entitlement_status_at_assignment', 32);
            $table->boolean('override_at_assignment')->default(false);
            $table->string('override_reason_at_assignment', 1000)->nullable();
            $table->foreignId('overridden_by_at_assignment')->nullable()->constrained('users', indexName: 'tr_passengers_overridden_at_asn_fk')->restrictOnDelete();
            $table->boolean('entitlement_override')->default(false);
            $table->string('entitlement_override_reason', 1000)->nullable();
            $table->foreignId('entitlement_overridden_by')->nullable()->constrained('users', indexName: 'tr_passengers_overridden_by_fk')->restrictOnDelete();
            $table->dateTime('entitlement_overridden_at')->nullable();
            $table->dateTime('assigned_at');
            $table->foreignId('assigned_by')->nullable()->constrained('users', indexName: 'tr_passengers_assigned_by_fk')->restrictOnDelete();
            $table->dateTime('boarded_at')->nullable();
            $table->dateTime('arrived_at')->nullable();
            $table->dateTime('no_show_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users', indexName: 'tr_passengers_cancelled_by_fk')->restrictOnDelete();
            $table->string('cancellation_reason', 1000)->nullable();
            $table->timestamps();

            $table->foreign(['tenant_id', 'season_id'], 'tr_passengers_season_fk')
                ->references(['tenant_id', 'id'])
                ->on('seasons')
                ->restrictOnDelete();
            $table->foreign(['season_id', 'vehicle_run_id'], 'tr_passengers_run_fk')
                ->references(['season_id', 'id'])
                ->on('transport_vehicle_runs')
                ->restrictOnDelete();
            $table->foreign(['tenant_id', 'season_id', 'season_pilgrim_id'], 'tr_passengers_season_pilgrim_fk')
                ->references(['tenant_id', 'season_id', 'id'])
                ->on('season_pilgrims')
                ->restrictOnDelete();

            $table->unique(['vehicle_run_id', 'id'], 'tr_passengers_run_id_unique');
            $table->unique(['vehicle_run_id', 'season_pilgrim_id', 'occupying_flag'], 'tr_passengers_occupying_unique');
            $table->index(['vehicle_run_id', 'status'], 'tr_passengers_run_status_index');
            $table->index(['tenant_id', 'season_id', 'season_pilgrim_id'], 'tr_passengers_season_pilgrim_index');
            $table->index(['season_id', 'program_id_at_assignment'], 'tr_passengers_season_program_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transport_run_passengers');
    }
};
