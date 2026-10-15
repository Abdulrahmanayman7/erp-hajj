<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only operational timeline (ADR-0018 T6, I7/I8). Rows are never
 * updated or deleted (no updated_at). A correction is a new row that
 * supersedes exactly one earlier row (UNIQUE supersedes_event_id).
 *
 * Composite FKs: the run belongs to the movement, the passenger to the run.
 *
 * @see docs/09-modules/17-transportation-and-fleet/DATA_MODEL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transport_timeline_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants', indexName: 'tr_timeline_tenant_fk')->restrictOnDelete();
            $table->unsignedBigInteger('season_id');
            $table->unsignedBigInteger('transport_movement_id');
            $table->unsignedBigInteger('vehicle_run_id')->nullable();
            $table->unsignedBigInteger('run_passenger_id')->nullable();
            $table->string('event_type', 64);
            $table->dateTime('occurred_at');
            $table->dateTime('recorded_at');
            $table->foreignId('actor_user_id')->nullable()->constrained('users', indexName: 'tr_timeline_actor_fk')->restrictOnDelete();
            $table->string('reason', 1000)->nullable();
            $table->json('context')->nullable();
            $table->foreignId('supersedes_event_id')->nullable()->constrained('transport_timeline_events', indexName: 'tr_timeline_supersedes_fk')->restrictOnDelete();
            $table->string('correlation_id', 100)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign(['tenant_id', 'season_id'], 'tr_timeline_season_fk')
                ->references(['tenant_id', 'id'])
                ->on('seasons')
                ->restrictOnDelete();
            $table->foreign(['season_id', 'transport_movement_id'], 'tr_timeline_movement_fk')
                ->references(['season_id', 'id'])
                ->on('transport_movements')
                ->restrictOnDelete();
            $table->foreign(['transport_movement_id', 'vehicle_run_id'], 'tr_timeline_run_fk')
                ->references(['transport_movement_id', 'id'])
                ->on('transport_vehicle_runs')
                ->restrictOnDelete();
            $table->foreign(['vehicle_run_id', 'run_passenger_id'], 'tr_timeline_passenger_fk')
                ->references(['vehicle_run_id', 'id'])
                ->on('transport_run_passengers')
                ->restrictOnDelete();

            $table->unique(['supersedes_event_id'], 'tr_timeline_supersedes_unique');
            $table->index(['season_id', 'transport_movement_id', 'id'], 'tr_timeline_movement_index');
            $table->index(['vehicle_run_id', 'run_passenger_id'], 'tr_timeline_run_passenger_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transport_timeline_events');
    }
};
