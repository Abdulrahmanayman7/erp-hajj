<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lock-anchor rows for confirmed-stay concurrency (Gate B).
 * One row per (tenant, season, season_pilgrim). Never stores occupancy payload.
 * Do not lock season_pilgrims for occupancy serialization.
 *
 * @see docs/reviews/ACCOMMODATION_SPRINT2_SCOPED_APPROVAL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accommodation_pilgrim_locks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants', indexName: 'acc_plock_tenant_fk')->restrictOnDelete();
            $table->unsignedBigInteger('season_id');
            $table->unsignedBigInteger('season_pilgrim_id');
            $table->timestamps();

            $table->unique(['tenant_id', 'id'], 'acc_plock_tenant_id_uq');
            $table->unique(['tenant_id', 'season_id', 'season_pilgrim_id'], 'acc_plock_pilgrim_uq');

            $table->foreign(['tenant_id', 'season_id'], 'acc_plock_season_fk')
                ->references(['tenant_id', 'id'])
                ->on('seasons')
                ->restrictOnDelete();
            $table->foreign(['tenant_id', 'season_id', 'season_pilgrim_id'], 'acc_plock_pilgrim_fk')
                ->references(['tenant_id', 'season_id', 'id'])
                ->on('season_pilgrims')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accommodation_pilgrim_locks');
    }
};
