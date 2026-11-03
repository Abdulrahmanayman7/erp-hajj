<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** S05 Part 3: purchase order headers. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('po_number', 20);
            $table->foreignId('procurement_rfq_id')->constrained('procurement_rfqs')->restrictOnDelete();
            $table->foreignId('purchase_request_id')->constrained('purchase_requests')->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('supplier_offer_id')->nullable()->constrained('supplier_offers')->restrictOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained('contracts')->restrictOnDelete();
            $table->foreignId('season_id')->nullable()->constrained('seasons')->restrictOnDelete();
            $table->string('status', 32);
            $table->char('currency', 3)->default('SAR');
            $table->text('notes')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('close_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'po_number'], 'purchase_orders_tenant_number_unique');
            // One PO per awarded RFQ (prevents duplicate PO from the same award).
            $table->unique(['tenant_id', 'procurement_rfq_id'], 'purchase_orders_tenant_rfq_unique');
            $table->index(['tenant_id', 'status'], 'purchase_orders_tenant_status_idx');
            $table->index(['tenant_id', 'purchase_request_id'], 'purchase_orders_tenant_pr_idx');
            $table->index(['tenant_id', 'supplier_id'], 'purchase_orders_tenant_supplier_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
