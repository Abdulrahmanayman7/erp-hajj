<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One pilgrim's participation in one season (F3, F5, F10).
 *
 * Composite foreign keys make cross-tenant and cross-season references
 * impossible at the database level:
 * - (tenant_id, season_id)  → seasons (tenant_id, id)
 * - (tenant_id, pilgrim_id) → pilgrims (tenant_id, id)
 * - (season_id, season_program_id / season_pilgrim_group_id /
 *    season_family_group_id) → the season's own program/group rows.
 *
 * Single-valued columns enforce "one current program", "at most one pilgrim
 * group" and "at most one family group" per SeasonPilgrim. The generated
 * `family_primary_flag` + UNIQUE (season_family_group_id, family_primary_flag)
 * enforces one primary member per family group.
 *
 * (tenant_id, id) and (tenant_id, season_id, id) are composite FK targets so
 * child rows cannot point at a participation of another tenant or season.
 *
 * @see docs/09-modules/16-season-and-pilgrims/DATA_MODEL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('season_pilgrims', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->unsignedBigInteger('season_id');
            $table->unsignedBigInteger('pilgrim_id');
            $table->string('reference_number', 32);
            $table->string('status', 32);
            $table->unsignedBigInteger('season_program_id')->nullable();
            $table->unsignedBigInteger('season_pilgrim_group_id')->nullable();
            $table->unsignedBigInteger('season_family_group_id')->nullable();
            $table->boolean('is_family_primary')->default(false);
            $table->unsignedTinyInteger('family_primary_flag')
                ->nullable()
                ->storedAs('case when is_family_primary = 1 then 1 else null end');
            $table->timestamp('registered_at');
            $table->foreignId('registered_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->foreignId('activated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('cancellation_reason', 1000)->nullable();
            $table->timestamps();

            $table->foreign(['tenant_id', 'season_id'], 'season_pilgrims_season_fk')
                ->references(['tenant_id', 'id'])
                ->on('seasons')
                ->restrictOnDelete();
            $table->foreign(['tenant_id', 'pilgrim_id'], 'season_pilgrims_pilgrim_fk')
                ->references(['tenant_id', 'id'])
                ->on('pilgrims')
                ->restrictOnDelete();
            $table->foreign(['season_id', 'season_program_id'], 'season_pilgrims_program_fk')
                ->references(['season_id', 'id'])
                ->on('season_programs')
                ->restrictOnDelete();
            $table->foreign(['season_id', 'season_pilgrim_group_id'], 'season_pilgrims_pilgrim_group_fk')
                ->references(['season_id', 'id'])
                ->on('season_pilgrim_groups')
                ->restrictOnDelete();
            $table->foreign(['season_id', 'season_family_group_id'], 'season_pilgrims_family_group_fk')
                ->references(['season_id', 'id'])
                ->on('season_family_groups')
                ->restrictOnDelete();

            // Composite FK targets for child tables (snapshot, program history).
            $table->unique(['tenant_id', 'id'], 'season_pilgrims_tenant_id_id_unique');
            $table->unique(['tenant_id', 'season_id', 'id'], 'season_pilgrims_tenant_season_id_unique');
            $table->unique(['tenant_id', 'season_id', 'pilgrim_id'], 'season_pilgrims_season_pilgrim_unique');
            $table->unique(['tenant_id', 'season_id', 'reference_number'], 'season_pilgrims_season_reference_unique');
            $table->unique(['season_family_group_id', 'family_primary_flag'], 'season_pilgrims_family_primary_unique');
            $table->index(['tenant_id', 'pilgrim_id']);
            $table->index(['tenant_id', 'season_id', 'status']);
            $table->index(['season_id', 'season_program_id']);
            $table->index(['season_id', 'season_pilgrim_group_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('season_pilgrims');
    }
};
