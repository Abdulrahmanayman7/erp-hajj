<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S10 U02 — project progress baselines (draft vs approved versions).
 * Approved checkpoint history is immutable; revisions create a new version.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('season_project_progress_baselines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants', indexName: 'sp_prog_base_tenant_fk')->restrictOnDelete();
            $table->unsignedBigInteger('season_project_id');
            $table->unsignedInteger('version');
            $table->string('status', 16)->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'sp_prog_base_created_by_fk')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users', indexName: 'sp_prog_base_approved_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->foreign('season_project_id', 'sp_prog_base_project_fk')
                ->references('id')
                ->on('season_projects')
                ->restrictOnDelete();

            $table->unique(['tenant_id', 'season_project_id', 'version'], 'sp_prog_base_tenant_project_ver_uq');
            $table->index(['tenant_id', 'season_project_id', 'status'], 'sp_prog_base_tenant_project_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('season_project_progress_baselines');
    }
};
