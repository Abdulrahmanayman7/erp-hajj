<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Materialized inventory balances (cache; ledger-authored).
 *
 * @see docs/09-modules/10-warehouses-and-inventory/DATA_MODEL.md
 * @see docs/10-decisions/ADR-0011-INVENTORY-LEDGER-BALANCE-AND-TRANSFER.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_balances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->decimal('on_hand', 18, 3)->default(0);
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'warehouse_id', 'inventory_item_id'],
                'inventory_balances_tenant_wh_item_unique',
            );
            $table->index(['tenant_id', 'warehouse_id']);
            $table->index(['tenant_id', 'inventory_item_id']);
            $table->index(['tenant_id', 'on_hand']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_balances');
    }
};
