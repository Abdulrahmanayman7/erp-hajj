<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only program assignment history per SeasonPilgrim (F22, F24).
 *
 * season_id is stored (immutable, copied from the participation) because it
 * is the only way to make the history structurally consistent:
 * - (tenant_id, season_id, season_pilgrim_id) → season_pilgrims (tenant_id, season_id, id)
 *   ties the row to a participation of the same tenant and season;
 * - (season_id, from/to_season_program_id) → season_programs (season_id, id)
 *   forbids recording a program of another season or tenant.
 *
 * @see docs/09-modules/16-season-and-pilgrims/DATA_MODEL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('season_pilgrim_program_changes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->unsignedBigInteger('season_id');
            $table->unsignedBigInteger('season_pilgrim_id');
            $table->unsignedBigInteger('from_season_program_id')->nullable();
            $table->unsignedBigInteger('to_season_program_id')->nullable();
            $table->string('reason', 1000)->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users', indexName: 'sp_program_changes_changed_by_fk')->restrictOnDelete();
            $table->string('correlation_id', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign(['tenant_id', 'season_id', 'season_pilgrim_id'], 'sp_program_changes_season_pilgrim_fk')
                ->references(['tenant_id', 'season_id', 'id'])
                ->on('season_pilgrims')
                ->restrictOnDelete();
            $table->foreign(['season_id', 'from_season_program_id'], 'sp_program_changes_from_program_fk')
                ->references(['season_id', 'id'])
                ->on('season_programs')
                ->restrictOnDelete();
            $table->foreign(['season_id', 'to_season_program_id'], 'sp_program_changes_to_program_fk')
                ->references(['season_id', 'id'])
                ->on('season_programs')
                ->restrictOnDelete();

            $table->index(['season_pilgrim_id', 'id'], 'sp_program_changes_season_pilgrim_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('season_pilgrim_program_changes');
    }
};
