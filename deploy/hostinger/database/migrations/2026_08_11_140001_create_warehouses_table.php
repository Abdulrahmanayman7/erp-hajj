<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant-owned warehouses.
 *
 * @see docs/09-modules/10-warehouses-and-inventory/DATA_MODEL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('warehouse_number', 20);
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('location', 500)->nullable();
            $table->foreignId('organization_unit_id')->nullable()->constrained('organization_units')->nullOnDelete();
            $table->foreignId('responsible_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'warehouse_number'], 'warehouses_tenant_number_unique');
            $table->index(['tenant_id', 'is_active']);
            $table->index(['tenant_id', 'organization_unit_id']);
            $table->index(['tenant_id', 'responsible_employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};
