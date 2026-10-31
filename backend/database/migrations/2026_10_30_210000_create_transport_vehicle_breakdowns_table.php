<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Operational vehicle breakdowns linked to a Run (ADR-0021 D03 / S02).
 * Additive; does not alter ADR-0018 T7 assignment history tables.
 *
 * @see docs/09-modules/17-transportation-and-fleet/DATA_MODEL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transport_vehicle_breakdowns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants', indexName: 'tr_bd_tenant_fk')->restrictOnDelete();
            $table->unsignedBigInteger('season_id');
            $table->unsignedBigInteger('vehicle_run_id');
            $table->unsignedBigInteger('transport_season_vehicle_id');
            $table->string('status', 16);
            $table->dateTime('occurred_at');
            $table->string('reason', 1000);
            $table->string('notes', 5000)->nullable();
            $table->foreignId('reported_by')->constrained('users', indexName: 'tr_bd_reported_by_fk')->restrictOnDelete();
            $table->unsignedBigInteger('replacement_id')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users', indexName: 'tr_bd_closed_by_fk')->restrictOnDelete();
            $table->string('closure_reason', 1000)->nullable();
            $table->timestamps();

            $table->foreign(['tenant_id', 'season_id'], 'tr_bd_season_fk')
                ->references(['tenant_id', 'id'])
                ->on('seasons')
                ->restrictOnDelete();
            $table->foreign(['season_id', 'vehicle_run_id'], 'tr_bd_run_fk')
                ->references(['season_id', 'id'])
                ->on('transport_vehicle_runs')
                ->restrictOnDelete();
            $table->foreign(['season_id', 'transport_season_vehicle_id'], 'tr_bd_season_vehicle_fk')
                ->references(['season_id', 'id'])
                ->on('transport_season_vehicles')
                ->restrictOnDelete();

            $table->index(['tenant_id', 'season_id', 'status'], 'tr_bd_season_status_index');
            $table->index(['vehicle_run_id', 'status'], 'tr_bd_run_status_index');
            $table->unique(['vehicle_run_id', 'id'], 'tr_bd_run_id_unique');
            $table->unique(['season_id', 'id'], 'tr_bd_season_id_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transport_vehicle_breakdowns');
    }
};
