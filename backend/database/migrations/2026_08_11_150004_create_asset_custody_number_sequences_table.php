<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-tenant asset custody number sequences (CUS-######).
 *
 * @see docs/09-modules/11-assets-and-custodies/DATA_MODEL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_custody_number_sequences', function (Blueprint $table): void {
            $table->foreignId('tenant_id')->primary()->constrained('tenants')->restrictOnDelete();
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_custody_number_sequences');
    }
};
