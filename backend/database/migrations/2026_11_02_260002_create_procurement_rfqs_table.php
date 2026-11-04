<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** S05 Part 1: procurement RFQ headers. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_rfqs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('rfq_number', 20);
            $table->foreignId('purchase_request_id')->constrained('purchase_requests')->restrictOnDelete();
            $table->foreignId('season_id')->nullable()->constrained('seasons')->restrictOnDelete();
            $table->string('status', 32);
            $table->string('title', 255);
            $table->text('notes')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('awarded_at')->nullable();
            $table->foreignId('awarded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('selection_rationale')->nullable();
            $table->foreignId('selected_supplier_id')->nullable()->constrained('suppliers')->restrictOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'rfq_number'], 'procurement_rfqs_tenant_number_unique');
            $table->index(['tenant_id', 'status'], 'procurement_rfqs_tenant_status_idx');
            $table->index(['tenant_id', 'purchase_request_id'], 'procurement_rfqs_tenant_pr_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_rfqs');
    }
};
