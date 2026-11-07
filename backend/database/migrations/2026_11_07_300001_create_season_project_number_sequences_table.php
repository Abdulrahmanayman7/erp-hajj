<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per tenant+season project number sequences (SPJ-######).
 *
 * @see docs/09-modules/20-season-projects/DATA_MODEL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('season_project_number_sequences', function (Blueprint $table): void {
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('season_id');
            $table->unsignedBigInteger('next_value')->default(1);
            $table->timestamps();

            $table->primary(['tenant_id', 'season_id'], 'season_project_number_sequences_pk');
            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->restrictOnDelete();
            $table->foreign(['tenant_id', 'season_id'], 'season_project_number_sequences_season_fk')
                ->references(['tenant_id', 'id'])
                ->on('seasons')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('season_project_number_sequences');
    }
};
