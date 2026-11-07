<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Operational risk register (S07).
 * project_id is nullable without FK — SeasonProjects may be unmerged (D08).
 *
 * @see docs/09-modules/21-risks-and-complaints/DATA_MODEL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_risks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->unsignedBigInteger('season_id');
            $table->string('risk_number', 32);
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('status', 32);
            $table->unsignedTinyInteger('likelihood')->nullable();
            $table->unsignedTinyInteger('impact')->nullable();
            $table->unsignedSmallInteger('score')->nullable();
            $table->string('severity_level', 16)->nullable();
            $table->string('closure_outcome', 32)->nullable();
            $table->text('close_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('owner_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('season_pilgrim_id')->nullable()->constrained('season_pilgrims')->nullOnDelete();
            $table->foreignId('group_id')->nullable()->constrained('season_pilgrim_groups')->nullOnDelete();
            $table->foreignId('facility_id')->nullable()->constrained('accommodation_facilities')->nullOnDelete();
            $table->foreignId('transport_season_vehicle_id')->nullable()->constrained('transport_season_vehicles')->nullOnDelete();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'id'], 'operational_risks_tenant_id_uq');
            $table->unique(['tenant_id', 'season_id', 'risk_number'], 'operational_risks_tenant_season_number_uq');
            $table->index(['tenant_id', 'season_id', 'status'], 'operational_risks_tenant_season_status_idx');
            $table->index(['tenant_id', 'severity_level'], 'operational_risks_tenant_severity_idx');
            $table->index(['tenant_id', 'owner_employee_id'], 'operational_risks_tenant_owner_idx');

            $table->foreign(['tenant_id', 'season_id'], 'operational_risks_season_fk')
                ->references(['tenant_id', 'id'])
                ->on('seasons')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_risks');
    }
};
