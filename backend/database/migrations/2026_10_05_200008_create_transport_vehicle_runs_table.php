<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One vehicle's execution inside a Movement (ADR-0018 T1/T6). Crew and
 * vehicle live in the assignment history tables, not on this row.
 *
 * @see docs/09-modules/17-transportation-and-fleet/DATA_MODEL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transport_vehicle_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants', indexName: 'tr_runs_tenant_fk')->restrictOnDelete();
            $table->unsignedBigInteger('season_id');
            $table->unsignedBigInteger('transport_movement_id');
            $table->unsignedSmallInteger('run_number');
            $table->string('status', 32);
            $table->unsignedSmallInteger('capacity_override')->nullable();
            $table->dateTime('boarding_started_at')->nullable();
            $table->dateTime('departed_at')->nullable();
            $table->dateTime('arrived_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->string('cancellation_reason', 1000)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'tr_runs_created_by_fk')->restrictOnDelete();
            $table->timestamps();

            $table->foreign(['tenant_id', 'season_id'], 'tr_runs_season_fk')
                ->references(['tenant_id', 'id'])
                ->on('seasons')
                ->restrictOnDelete();
            $table->foreign(['season_id', 'transport_movement_id'], 'tr_runs_movement_fk')
                ->references(['season_id', 'id'])
                ->on('transport_movements')
                ->restrictOnDelete();

            $table->unique(['season_id', 'id'], 'tr_runs_season_id_unique');
            $table->unique(['transport_movement_id', 'id'], 'tr_runs_movement_id_unique');
            $table->unique(['transport_movement_id', 'run_number'], 'tr_runs_movement_number_unique');
            $table->index(['season_id', 'status'], 'tr_runs_season_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transport_vehicle_runs');
    }
};
