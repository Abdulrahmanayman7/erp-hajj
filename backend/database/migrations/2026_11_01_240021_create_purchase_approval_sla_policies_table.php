<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** S04 Phase IV WP05: tenant SLA policies by priority × level_order (no platform defaults). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_approval_sla_policies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('priority', 32);
            $table->unsignedInteger('level_order');
            $table->unsignedInteger('target_hours');
            $table->unsignedInteger('reminder_offset_hours')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'priority', 'level_order'], 'pasp_tenant_priority_level_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_approval_sla_policies');
    }
};
