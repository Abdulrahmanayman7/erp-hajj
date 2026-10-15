<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Needs Review" items raised when history cannot be changed (ADR-0018
 * T8/T10, I11). UNIQUE (run_passenger_id, review_type) keeps the
 * cancellation handler idempotent.
 *
 * @see docs/09-modules/17-transportation-and-fleet/DATA_MODEL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transport_review_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants', indexName: 'tr_reviews_tenant_fk')->restrictOnDelete();
            $table->unsignedBigInteger('season_id');
            $table->unsignedBigInteger('vehicle_run_id');
            $table->unsignedBigInteger('run_passenger_id');
            $table->unsignedBigInteger('season_pilgrim_id');
            $table->string('review_type', 64);
            $table->string('severity', 16);
            $table->string('status', 16);
            $table->json('details')->nullable();
            $table->dateTime('raised_at');
            $table->dateTime('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users', indexName: 'tr_reviews_resolved_by_fk')->restrictOnDelete();
            $table->string('resolution_note', 1000)->nullable();
            $table->timestamps();

            $table->foreign(['tenant_id', 'season_id'], 'tr_reviews_season_fk')
                ->references(['tenant_id', 'id'])
                ->on('seasons')
                ->restrictOnDelete();
            $table->foreign(['season_id', 'vehicle_run_id'], 'tr_reviews_run_fk')
                ->references(['season_id', 'id'])
                ->on('transport_vehicle_runs')
                ->restrictOnDelete();
            $table->foreign(['vehicle_run_id', 'run_passenger_id'], 'tr_reviews_passenger_fk')
                ->references(['vehicle_run_id', 'id'])
                ->on('transport_run_passengers')
                ->restrictOnDelete();
            $table->foreign(['tenant_id', 'season_id', 'season_pilgrim_id'], 'tr_reviews_season_pilgrim_fk')
                ->references(['tenant_id', 'season_id', 'id'])
                ->on('season_pilgrims')
                ->restrictOnDelete();

            $table->unique(['run_passenger_id', 'review_type'], 'tr_reviews_passenger_type_unique');
            $table->index(['season_id', 'status', 'severity'], 'tr_reviews_season_status_index');
            $table->index(['tenant_id', 'season_id', 'season_pilgrim_id'], 'tr_reviews_season_pilgrim_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transport_review_items');
    }
};
