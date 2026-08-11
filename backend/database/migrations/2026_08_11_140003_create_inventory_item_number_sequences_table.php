<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-tenant inventory item number sequences (ITM-######).
 *
 * @see docs/09-modules/10-warehouses-and-inventory/DATA_MODEL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_item_number_sequences', function (Blueprint $table): void {
            $table->foreignId('tenant_id')->primary()->constrained('tenants')->restrictOnDelete();
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_item_number_sequences');
    }
};
