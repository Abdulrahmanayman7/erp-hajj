<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-tenant meeting number sequences (MTG-######).
 *
 * @see docs/09-modules/06-meetings/DATA_MODEL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_number_sequences', function (Blueprint $table): void {
            $table->foreignId('tenant_id')->primary()->constrained('tenants')->restrictOnDelete();
            $table->unsignedBigInteger('next_value')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_number_sequences');
    }
};
