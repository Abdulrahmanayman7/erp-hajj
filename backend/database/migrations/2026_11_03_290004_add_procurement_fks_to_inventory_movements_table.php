<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S05 Part 4: optional PO/GRN FKs on inventory ledger + close GRN line → movement FK.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->foreignId('purchase_order_id')
                ->nullable()
                ->after('correlation_id')
                ->constrained('purchase_orders')
                ->restrictOnDelete();
            $table->foreignId('goods_receipt_id')
                ->nullable()
                ->after('purchase_order_id')
                ->constrained('goods_receipts')
                ->restrictOnDelete();
            $table->foreignId('goods_receipt_line_id')
                ->nullable()
                ->after('goods_receipt_id')
                ->constrained('goods_receipt_lines')
                ->restrictOnDelete();

            $table->unique(['tenant_id', 'goods_receipt_line_id'], 'inventory_movements_tenant_grn_line_unique');
            $table->index(['tenant_id', 'purchase_order_id'], 'inventory_movements_tenant_po_idx');
            $table->index(['tenant_id', 'goods_receipt_id'], 'inventory_movements_tenant_grn_idx');
        });

        Schema::table('goods_receipt_lines', function (Blueprint $table): void {
            $table->foreign('inventory_movement_id', 'goods_receipt_lines_movement_fk')
                ->references('id')
                ->on('inventory_movements')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('goods_receipt_lines', function (Blueprint $table): void {
            $table->dropForeign('goods_receipt_lines_movement_fk');
        });

        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->dropUnique('inventory_movements_tenant_grn_line_unique');
            $table->dropIndex('inventory_movements_tenant_po_idx');
            $table->dropIndex('inventory_movements_tenant_grn_idx');
            $table->dropConstrainedForeignId('goods_receipt_line_id');
            $table->dropConstrainedForeignId('goods_receipt_id');
            $table->dropConstrainedForeignId('purchase_order_id');
        });
    }
};
