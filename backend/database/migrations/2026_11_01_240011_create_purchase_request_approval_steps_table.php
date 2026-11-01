<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** S04 Phase IV WP02: ordered approval steps (pool model — no assignee). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_request_approval_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('instance_id')->constrained('purchase_request_approval_instances')->restrictOnDelete();
            $table->unsignedInteger('level_order');
            $table->string('status', 32)->default('pending');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->foreignId('acted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('acted_at')->nullable();
            $table->text('comment')->nullable();
            $table->text('decision_reason')->nullable();
            $table->string('conflict_code', 64)->nullable();
            $table->timestamps();

            $table->unique(['instance_id', 'level_order'], 'pras_instance_level_unique');
            $table->index(['tenant_id', 'status'], 'pras_tenant_status_idx');
            $table->index(['tenant_id', 'due_at'], 'pras_tenant_due_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_request_approval_steps');
    }
};
