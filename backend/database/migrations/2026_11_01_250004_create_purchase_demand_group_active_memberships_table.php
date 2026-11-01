<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S04 Phase V: exclusive active membership index (MariaDB 10.4 — no partial unique).
 * UNIQUE/PRIMARY(tenant_id, purchase_request_id) enforces ≤1 active group per PR header.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_demand_group_active_memberships', function (Blueprint $table): void {
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('purchase_request_id');
            $table->unsignedBigInteger('demand_group_id');
            $table->unsignedBigInteger('item_id');
            $table->timestamp('created_at')->useCurrent();

            $table->primary(['tenant_id', 'purchase_request_id'], 'pdgam_tenant_pr_pk');
            $table->index(['tenant_id', 'demand_group_id'], 'pdgam_tenant_group_idx');

            $table->foreign('tenant_id', 'pdgam_tenant_fk')
                ->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign('purchase_request_id', 'pdgam_pr_fk')
                ->references('id')->on('purchase_requests')->restrictOnDelete();
            $table->foreign('demand_group_id', 'pdgam_group_fk')
                ->references('id')->on('purchase_demand_groups')->restrictOnDelete();
            $table->foreign('item_id', 'pdgam_item_fk')
                ->references('id')->on('purchase_demand_group_items')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_demand_group_active_memberships');
    }
};
