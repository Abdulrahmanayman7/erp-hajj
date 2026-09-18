<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only task assignment history.
 *
 * @see docs/09-modules/08-tasks/DATA_MODEL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_assignment_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('from_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('to_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('performed_by')->constrained('users')->restrictOnDelete();
            $table->text('comment')->nullable();
            $table->string('correlation_id', 64);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'task_id', 'created_at'], 'task_assignment_hist_tenant_task_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_assignment_history');
    }
};
