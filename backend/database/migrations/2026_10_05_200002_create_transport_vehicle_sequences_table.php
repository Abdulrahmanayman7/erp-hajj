<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-tenant vehicle number sequence (VEH-######). Never decremented.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transport_vehicle_sequences', function (Blueprint $table): void {
            $table->foreignId('tenant_id')->primary()->constrained('tenants', indexName: 'tr_vehicle_seq_tenant_fk')->restrictOnDelete();
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transport_vehicle_sequences');
    }
};
