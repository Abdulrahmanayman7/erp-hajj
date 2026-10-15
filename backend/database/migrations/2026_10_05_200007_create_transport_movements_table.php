<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Transportation Movement (ADR-0018 T1/T6). Status is persisted, never
 * derived from its Runs.
 *
 * @see docs/09-modules/17-transportation-and-fleet/DATA_MODEL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transport_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants', indexName: 'tr_movements_tenant_fk')->restrictOnDelete();
            $table->unsignedBigInteger('season_id');
            $table->string('movement_number', 32);
            $table->unsignedBigInteger('origin_point_id');
            $table->unsignedBigInteger('destination_point_id');
            $table->dateTime('planned_departure_at');
            $table->dateTime('planned_arrival_at');
            $table->string('status', 32);
            $table->dateTime('planned_at')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->string('cancellation_reason', 1000)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'tr_movements_created_by_fk')->restrictOnDelete();
            $table->timestamps();

            $table->foreign(['tenant_id', 'season_id'], 'tr_movements_season_fk')
                ->references(['tenant_id', 'id'])
                ->on('seasons')
                ->restrictOnDelete();
            $table->foreign(['tenant_id', 'origin_point_id'], 'tr_movements_origin_fk')
                ->references(['tenant_id', 'id'])
                ->on('transport_points')
                ->restrictOnDelete();
            $table->foreign(['tenant_id', 'destination_point_id'], 'tr_movements_destination_fk')
                ->references(['tenant_id', 'id'])
                ->on('transport_points')
                ->restrictOnDelete();

            $table->unique(['season_id', 'id'], 'tr_movements_season_id_unique');
            $table->unique(['tenant_id', 'season_id', 'movement_number'], 'tr_movements_season_number_unique');
            $table->index(['tenant_id', 'season_id', 'status'], 'tr_movements_season_status_index');
            $table->index(['tenant_id', 'origin_point_id'], 'tr_movements_tenant_origin_index');
            $table->index(['tenant_id', 'destination_point_id'], 'tr_movements_tenant_dest_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transport_movements');
    }
};
