<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** S05 Part 3: purchase order lines (copied from winning offer). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->unsignedInteger('line_no');
            $table->foreignId('procurement_rfq_line_id')->nullable()->constrained('procurement_rfq_lines')->restrictOnDelete();
            $table->foreignId('supplier_offer_line_id')->nullable()->constrained('supplier_offer_lines')->restrictOnDelete();
            $table->string('description', 500);
            $table->text('specifications')->nullable();
            $table->decimal('quantity_ordered', 18, 4);
            $table->decimal('quantity_received', 18, 4)->default(0);
            $table->string('unit', 50);
            $table->decimal('unit_price', 18, 4);
            $table->foreignId('inventory_item_id')->nullable()->constrained('inventory_items')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['purchase_order_id', 'line_no'], 'purchase_order_lines_po_line_unique');
            $table->index(['tenant_id', 'purchase_order_id'], 'purchase_order_lines_tenant_po_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_lines');
    }
};
