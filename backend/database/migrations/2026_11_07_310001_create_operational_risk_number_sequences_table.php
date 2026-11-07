<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-tenant, per-season, per-year risk number sequences (S07 D06).
 * Sequence only advances; soft-deleted risk numbers are never reused.
 *
 * @see docs/09-modules/21-risks-and-complaints/DATA_MODEL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_risk_number_sequences', function (Blueprint $table): void {
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->unsignedBigInteger('season_id');
            $table->unsignedSmallInteger('year');
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();

            $table->primary(['tenant_id', 'season_id', 'year']);
            $table->foreign(['tenant_id', 'season_id'], 'operational_risk_number_sequences_season_fk')
                ->references(['tenant_id', 'id'])
                ->on('seasons')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_risk_number_sequences');
    }
};
