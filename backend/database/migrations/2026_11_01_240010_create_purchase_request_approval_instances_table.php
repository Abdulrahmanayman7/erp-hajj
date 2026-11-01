<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** S04 Phase IV WP02: approval instances (one per PR version). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_request_approval_instances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('purchase_request_id')->constrained('purchase_requests')->restrictOnDelete();
            $table->unsignedInteger('version_no');
            $table->foreignId('matrix_id')->constrained('purchase_approval_matrices')->restrictOnDelete();
            $table->unsignedInteger('matrix_version_no');
            $table->foreignId('rule_id')->constrained('purchase_approval_matrix_rules')->restrictOnDelete();
            $table->string('status', 32)->default('active');
            $table->timestamp('routed_at');
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'purchase_request_id', 'version_no'],
                'prai_tenant_pr_version_unique',
            );
            $table->index(['tenant_id', 'status'], 'prai_tenant_status_idx');
            $table->index(['purchase_request_id', 'version_no'], 'prai_pr_version_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_request_approval_instances');
    }
};
