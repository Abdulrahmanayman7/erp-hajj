<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-tenant contract number sequences (CTR-######).
 *
 * @see docs/09-modules/05-contracts/DATA_MODEL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_number_sequences', function (Blueprint $table): void {
            $table->foreignId('tenant_id')->primary()->constrained('tenants')->restrictOnDelete();
            $table->unsignedBigInteger('next_value')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_number_sequences');
    }
};
