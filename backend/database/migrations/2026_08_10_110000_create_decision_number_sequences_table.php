<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-tenant decision number sequences (DEC-######).
 *
 * @see docs/09-modules/07-decisions/DATA_MODEL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('decision_number_sequences', function (Blueprint $table): void {
            $table->foreignId('tenant_id')->primary()->constrained('tenants')->restrictOnDelete();
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('decision_number_sequences');
    }
};
