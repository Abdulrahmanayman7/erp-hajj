<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only inventory movement ledger.
 *
 * @see docs/09-modules/10-warehouses-and-inventory/DATA_MODEL.md
 * @see docs/10-decisions/ADR-0011-INVENTORY-LEDGER-BALANCE-AND-TRANSFER.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('movement_number', 20);
            $table->string('type', 32);
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->decimal('quantity', 18, 3);
            $table->string('direction', 8);
            $table->decimal('balance_before', 18, 3);
            $table->decimal('balance_after', 18, 3);
            $table->char('transfer_group_id', 36)->nullable();
            $table->string('reason', 1000);
            $table->string('reference', 255)->nullable();
            $table->foreignId('performed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('occurred_at');
            $table->string('correlation_id', 64)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'movement_number'], 'inventory_movements_tenant_number_unique');
            $table->index(['tenant_id', 'warehouse_id', 'occurred_at'], 'inventory_movements_tenant_wh_occurred_idx');
            $table->index(['tenant_id', 'inventory_item_id', 'occurred_at'], 'inventory_movements_tenant_item_occurred_idx');
            $table->index(['tenant_id', 'type']);
            $table->index(['tenant_id', 'transfer_group_id']);
            $table->index(['tenant_id', 'performed_by']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
