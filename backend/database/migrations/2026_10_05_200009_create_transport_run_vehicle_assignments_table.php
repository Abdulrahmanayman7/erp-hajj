<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Run ↔ SeasonVehicle assignment history (ADR-0018 T7). `current_flag`
 * (1 while ended_at is NULL) + UNIQUE (vehicle_run_id, current_flag) allows
 * at most one current vehicle per Run. Overlap across Runs is checked in the
 * action under row locks (time ranges cannot be a UNIQUE).
 *
 * @see docs/09-modules/17-transportation-and-fleet/DATA_MODEL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transport_run_vehicle_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants', indexName: 'tr_run_vehicles_tenant_fk')->restrictOnDelete();
            $table->unsignedBigInteger('season_id');
            $table->unsignedBigInteger('vehicle_run_id');
            $table->unsignedBigInteger('transport_season_vehicle_id');
            $table->dateTime('assigned_at');
            $table->foreignId('assigned_by')->nullable()->constrained('users', indexName: 'tr_run_vehicles_assigned_by_fk')->restrictOnDelete();
            $table->dateTime('ended_at')->nullable();
            $table->foreignId('ended_by')->nullable()->constrained('users', indexName: 'tr_run_vehicles_ended_by_fk')->restrictOnDelete();
            $table->string('reason', 1000)->nullable();
            $table->unsignedTinyInteger('current_flag')
                ->nullable()
                ->storedAs('case when ended_at is null then 1 else null end');
            $table->timestamps();

            $table->foreign(['tenant_id', 'season_id'], 'tr_run_vehicles_season_fk')
                ->references(['tenant_id', 'id'])
                ->on('seasons')
                ->restrictOnDelete();
            $table->foreign(['season_id', 'vehicle_run_id'], 'tr_run_vehicles_run_fk')
                ->references(['season_id', 'id'])
                ->on('transport_vehicle_runs')
                ->restrictOnDelete();
            $table->foreign(['season_id', 'transport_season_vehicle_id'], 'tr_run_vehicles_season_vehicle_fk')
                ->references(['season_id', 'id'])
                ->on('transport_season_vehicles')
                ->restrictOnDelete();

            $table->unique(['vehicle_run_id', 'current_flag'], 'tr_run_vehicles_current_unique');
            $table->index(['season_id', 'transport_season_vehicle_id', 'ended_at'], 'tr_run_vehicles_vehicle_current_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transport_run_vehicle_assignments');
    }
};
