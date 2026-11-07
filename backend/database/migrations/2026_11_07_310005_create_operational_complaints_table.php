<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Operational complaints register (S07).
 * project_id is nullable without FK — SeasonProjects may be unmerged (D08).
 *
 * @see docs/09-modules/21-risks-and-complaints/DATA_MODEL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_complaints', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->unsignedBigInteger('season_id');
            $table->string('complaint_number', 32);
            $table->string('title', 200);
            $table->text('body');
            $table->string('category_code', 64);
            $table->string('status', 32);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('assignee_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->unsignedInteger('escalation_level')->default(0);
            $table->foreignId('escalated_to_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->text('escalate_reason')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->text('resolution_text')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('close_reason')->nullable();
            $table->timestamp('reopened_at')->nullable();
            $table->foreignId('reopened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reopen_reason')->nullable();
            $table->foreignId('season_pilgrim_id')->nullable()->constrained('season_pilgrims')->nullOnDelete();
            $table->foreignId('group_id')->nullable()->constrained('season_pilgrim_groups')->nullOnDelete();
            $table->foreignId('facility_id')->nullable()->constrained('accommodation_facilities')->nullOnDelete();
            $table->foreignId('transport_season_vehicle_id')->nullable()->constrained('transport_season_vehicles')->nullOnDelete();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'id'], 'operational_complaints_tenant_id_uq');
            $table->unique(['tenant_id', 'season_id', 'complaint_number'], 'operational_complaints_tenant_season_number_uq');
            $table->index(['tenant_id', 'season_id', 'status'], 'operational_complaints_tenant_season_status_idx');
            $table->index(['tenant_id', 'assignee_employee_id'], 'operational_complaints_tenant_assignee_idx');
            $table->index(['tenant_id', 'category_code'], 'operational_complaints_tenant_category_idx');

            $table->foreign(['tenant_id', 'season_id'], 'operational_complaints_season_fk')
                ->references(['tenant_id', 'id'])
                ->on('seasons')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_complaints');
    }
};
