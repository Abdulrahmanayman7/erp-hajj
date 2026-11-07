<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Informational phases under a season project (S06 Part 1).
 * Phase completion workflow is deferred (E-Q03).
 *
 * @see docs/09-modules/20-season-projects/DATA_MODEL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('season_project_phases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->unsignedBigInteger('season_id');
            $table->foreignId('project_id')->constrained('season_projects')->restrictOnDelete();
            $table->string('title', 255);
            $table->unsignedInteger('sort_order')->default(0);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('status', 32)->default('planned');
            $table->timestamps();

            $table->foreign(['tenant_id', 'season_id'], 'season_project_phases_season_fk')
                ->references(['tenant_id', 'id'])
                ->on('seasons')
                ->restrictOnDelete();

            $table->unique(['project_id', 'sort_order'], 'season_project_phases_project_sort_unique');
            $table->index(['tenant_id', 'project_id'], 'season_project_phases_tenant_project_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('season_project_phases');
    }
};
