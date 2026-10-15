<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-tenant, per-season seasonal reference number sequences (F32).
 * Numbers are never decremented, so a cancelled/deleted participation's
 * reference is never reused.
 *
 * @see docs/09-modules/16-season-and-pilgrims/DATA_MODEL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('season_pilgrim_reference_sequences', function (Blueprint $table): void {
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->unsignedBigInteger('season_id');
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();

            $table->primary(['tenant_id', 'season_id']);
            $table->foreign(['tenant_id', 'season_id'], 'season_pilgrim_reference_sequences_season_fk')
                ->references(['tenant_id', 'id'])
                ->on('seasons')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('season_pilgrim_reference_sequences');
    }
};
