<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** S05 Part 1: RFQ supplier invitations (schema; invite workflows expand in Part 2). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_rfq_invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('procurement_rfq_id')->constrained('procurement_rfqs')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->string('status', 32)->default('invited');
            $table->timestamp('invited_at');
            $table->foreignId('invited_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'procurement_rfq_id', 'supplier_id'], 'procurement_rfq_invitations_unique');
            $table->index(['tenant_id', 'procurement_rfq_id'], 'procurement_rfq_invitations_rfq_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_rfq_invitations');
    }
};
