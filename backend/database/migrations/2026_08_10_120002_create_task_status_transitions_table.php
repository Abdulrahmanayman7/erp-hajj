<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only task lifecycle history.
 *
 * @see docs/09-modules/08-tasks/DATA_MODEL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_status_transitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('comment')->nullable();
            $table->string('correlation_id', 64);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'task_id', 'created_at'], 'task_transitions_tenant_task_created_idx');
            $table->index(['task_id', 'created_at'], 'task_transitions_task_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_status_transitions');
    }
};
