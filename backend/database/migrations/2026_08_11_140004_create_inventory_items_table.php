<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant-owned inventory items.
 *
 * @see docs/09-modules/10-warehouses-and-inventory/DATA_MODEL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('item_number', 20);
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('inventory_categories')->restrictOnDelete();
            $table->string('unit', 32);
            $table->string('barcode', 64)->nullable();
            $table->decimal('minimum_stock', 18, 3)->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'item_number'], 'inventory_items_tenant_number_unique');
            $table->unique(['tenant_id', 'barcode'], 'inventory_items_tenant_barcode_unique');
            $table->index(['tenant_id', 'is_active']);
            $table->index(['tenant_id', 'category_id']);
            $table->index(['tenant_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
