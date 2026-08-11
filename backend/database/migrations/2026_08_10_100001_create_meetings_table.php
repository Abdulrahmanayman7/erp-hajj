<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant-owned meetings.
 *
 * @see docs/09-modules/06-meetings/DATA_MODEL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meetings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('meeting_number', 32);
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft');
            $table->dateTime('scheduled_at')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('ended_at')->nullable();
            $table->string('location_type', 20)->default('physical');
            $table->string('location_text', 255)->nullable();
            $table->string('meeting_link', 500)->nullable();
            $table->foreignId('organization_unit_id')->nullable()->constrained('organization_units')->restrictOnDelete();
            $table->foreignId('chairperson_employee_id')->nullable()->constrained('employees')->restrictOnDelete();
            $table->foreignId('secretary_employee_id')->nullable()->constrained('employees')->restrictOnDelete();
            $table->text('minutes_body')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'meeting_number'], 'meetings_tenant_number_unique');
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'scheduled_at']);
            $table->index(['tenant_id', 'organization_unit_id']);
            $table->index(['tenant_id', 'chairperson_employee_id']);
            $table->index(['tenant_id', 'title']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meetings');
    }
};
