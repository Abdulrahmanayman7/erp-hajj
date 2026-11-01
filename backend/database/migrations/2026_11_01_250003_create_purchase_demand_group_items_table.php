<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** S04 Phase V: demand group membership history (link/unlink rows). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_demand_group_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('demand_group_id')->constrained('purchase_demand_groups')->restrictOnDelete();
            $table->foreignId('purchase_request_id')->constrained('purchase_requests')->restrictOnDelete();
            $table->unsignedInteger('pr_version_no_at_link');
            $table->timestamp('linked_at');
            $table->foreignId('linked_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('unlinked_at')->nullable();
            $table->foreignId('unlinked_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('unlink_reason', 64)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'demand_group_id'], 'pdgi_tenant_group_idx');
            $table->index(['tenant_id', 'purchase_request_id'], 'pdgi_tenant_pr_idx');
            $table->index(['demand_group_id', 'unlinked_at'], 'pdgi_group_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_demand_group_items');
    }
};
