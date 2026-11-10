<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S10 U02 — dated planned progress checkpoints belonging to a baseline version.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('season_project_progress_checkpoints', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants', indexName: 'sp_prog_cp_tenant_fk')->restrictOnDelete();
            $table->unsignedBigInteger('baseline_id');
            $table->timestamp('effective_at');
            $table->decimal('planned_progress_percent', 5, 2);
            $table->timestamps();

            $table->foreign('baseline_id', 'sp_prog_cp_baseline_fk')
                ->references('id')
                ->on('season_project_progress_baselines')
                ->restrictOnDelete();

            $table->unique(['baseline_id', 'effective_at'], 'sp_prog_cp_baseline_effective_uq');
            $table->index(['tenant_id', 'baseline_id', 'effective_at'], 'sp_prog_cp_tenant_base_eff_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('season_project_progress_checkpoints');
    }
};
