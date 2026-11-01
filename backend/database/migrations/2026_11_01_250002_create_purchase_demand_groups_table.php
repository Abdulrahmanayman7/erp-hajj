<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** S04 Phase V: purchase demand groups (planning-only PR header collections). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_demand_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('group_number', 32);
            $table->string('title');
            $table->text('notes')->nullable();
            $table->string('status', 32)->default('draft');
            $table->foreignId('season_id')->nullable()->constrained('seasons')->restrictOnDelete();
            $table->foreignId('beneficiary_organization_unit_id')->nullable()->constrained('organization_units')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('frozen_at')->nullable();
            $table->foreignId('frozen_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('freeze_reason', 64)->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'group_number'], 'pdg_tenant_number_unique');
            $table->index(['tenant_id', 'status'], 'pdg_tenant_status_idx');
            $table->index(['tenant_id', 'season_id'], 'pdg_tenant_season_idx');
            $table->index(['tenant_id', 'beneficiary_organization_unit_id'], 'pdg_tenant_ou_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_demand_groups');
    }
};
