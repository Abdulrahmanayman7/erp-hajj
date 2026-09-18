<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-tenant task number sequences (TSK-######).
 *
 * @see docs/09-modules/08-tasks/DATA_MODEL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_number_sequences', function (Blueprint $table): void {
            $table->foreignId('tenant_id')->primary()->constrained('tenants')->restrictOnDelete();
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_number_sequences');
    }
};
