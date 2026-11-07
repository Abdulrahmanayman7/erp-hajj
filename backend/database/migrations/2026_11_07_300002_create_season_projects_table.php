<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Season-scoped operational projects (S06 Part 1).
 *
 * @see docs/09-modules/20-season-projects/DATA_MODEL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('season_projects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->unsignedBigInteger('season_id');
            $table->string('number', 20);
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('status', 32)->default('draft');
            $table->foreignId('owner_employee_id')->nullable()->constrained('employees')->restrictOnDelete();
            $table->decimal('budget_amount', 18, 2)->nullable();
            $table->char('currency_code', 3)->default('SAR');
            $table->decimal('progress_percent', 5, 2)->default(0);
            $table->string('delay_summary', 32)->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign(['tenant_id', 'season_id'], 'season_projects_season_fk')
                ->references(['tenant_id', 'id'])
                ->on('seasons')
                ->restrictOnDelete();

            // Includes soft-deleted rows — project numbers are never reused (Owner).
            $table->unique(['tenant_id', 'season_id', 'number'], 'season_projects_tenant_season_number_unique');
            $table->index(['tenant_id', 'season_id', 'status'], 'season_projects_tenant_season_status_idx');
            $table->index(['tenant_id', 'owner_employee_id'], 'season_projects_tenant_owner_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('season_projects');
    }
};
