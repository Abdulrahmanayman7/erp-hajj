<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant-owned assets (current_custody_id FK added after asset_custodies).
 *
 * @see docs/09-modules/11-assets-and-custodies/DATA_MODEL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('asset_number', 20);
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('asset_categories')->restrictOnDelete();
            $table->string('serial_number', 120)->nullable();
            $table->string('barcode', 64)->nullable();
            $table->string('status', 32);
            $table->string('condition', 32)->nullable();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('organization_unit_id')->nullable()->constrained('organization_units')->nullOnDelete();
            $table->decimal('purchase_value', 18, 2)->nullable();
            $table->date('acquisition_date')->nullable();
            $table->unsignedBigInteger('current_custody_id')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'asset_number'], 'assets_tenant_number_unique');
            $table->unique(['tenant_id', 'serial_number'], 'assets_tenant_serial_unique');
            $table->unique(['tenant_id', 'barcode'], 'assets_tenant_barcode_unique');
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'category_id']);
            $table->index(['tenant_id', 'warehouse_id']);
            $table->index(['tenant_id', 'organization_unit_id']);
            $table->index(['tenant_id', 'name']);
            $table->index(['tenant_id', 'current_custody_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
