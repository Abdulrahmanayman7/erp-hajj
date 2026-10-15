<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant-level vehicle registry (ADR-0018 T3). Asset / Contract links are
 * optional, read-only references; those modules stay authoritative, so the
 * links are cleared when the referenced row is deleted (I12). `assets` and
 * `contracts` have no (tenant_id, id) key, so the linking action enforces
 * same-tenant through the tenant-scoped queries.
 *
 * @see docs/09-modules/17-transportation-and-fleet/DATA_MODEL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transport_vehicles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants', indexName: 'tr_vehicles_tenant_fk')->restrictOnDelete();
            $table->string('vehicle_number', 32);
            $table->string('plate_number', 32);
            $table->string('vehicle_class', 32);
            $table->foreignId('asset_id')->nullable()->constrained('assets', indexName: 'tr_vehicles_asset_fk')->nullOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained('contracts', indexName: 'tr_vehicles_contract_fk')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'tr_vehicles_created_by_fk')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'id'], 'tr_vehicles_tenant_id_unique');
            $table->unique(['tenant_id', 'vehicle_number'], 'tr_vehicles_tenant_number_unique');
            $table->unique(['tenant_id', 'plate_number'], 'tr_vehicles_tenant_plate_unique');
            $table->unique(['asset_id'], 'tr_vehicles_asset_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transport_vehicles');
    }
};
