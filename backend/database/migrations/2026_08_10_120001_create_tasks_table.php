<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant-owned execution tasks.
 *
 * @see docs/09-modules/08-tasks/DATA_MODEL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('task_number', 20);
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 32)->default('draft');
            $table->string('priority', 16)->default('medium');
            $table->foreignId('decision_id')->nullable()->constrained('decisions')->restrictOnDelete();
            $table->foreignId('organization_unit_id')->nullable()->constrained('organization_units')->restrictOnDelete();
            $table->foreignId('assigned_to_employee_id')->nullable()->constrained('employees')->restrictOnDelete();
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('completion_notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'task_number'], 'tasks_tenant_number_unique');
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'decision_id']);
            $table->index(['tenant_id', 'assigned_to_employee_id']);
            $table->index(['tenant_id', 'organization_unit_id']);
            $table->index(['tenant_id', 'due_date']);
            $table->index(['tenant_id', 'priority']);
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
