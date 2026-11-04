<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** S05 Part 4: goods receipt lines. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_receipt_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('goods_receipt_id')->constrained('goods_receipts')->cascadeOnDelete();
            $table->unsignedInteger('line_no');
            $table->foreignId('purchase_order_line_id')->constrained('purchase_order_lines')->restrictOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->foreignId('inventory_item_id')->nullable()->constrained('inventory_items')->restrictOnDelete();
            // FK to inventory_movements added after ledger columns exist (avoid circular create order).
            $table->unsignedBigInteger('inventory_movement_id')->nullable();
            $table->timestamps();

            $table->unique(['goods_receipt_id', 'line_no'], 'goods_receipt_lines_grn_line_unique');
            $table->unique(['goods_receipt_id', 'purchase_order_line_id'], 'goods_receipt_lines_grn_po_line_unique');
            $table->unique(['inventory_movement_id'], 'goods_receipt_lines_movement_unique');
            $table->index(['tenant_id', 'goods_receipt_id'], 'goods_receipt_lines_tenant_grn_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_lines');
    }
};
