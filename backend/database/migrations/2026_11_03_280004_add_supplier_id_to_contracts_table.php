<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S05 Part 3: optional nullable contracts.supplier_id bridge (no backfill).
 * Does not change Contracts lifecycle transitions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->foreignId('supplier_id')
                ->nullable()
                ->after('organization_unit_id')
                ->constrained('suppliers')
                ->restrictOnDelete();

            $table->index(['tenant_id', 'supplier_id'], 'contracts_tenant_supplier_idx');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->dropIndex('contracts_tenant_supplier_idx');
            $table->dropConstrainedForeignId('supplier_id');
        });
    }
};
