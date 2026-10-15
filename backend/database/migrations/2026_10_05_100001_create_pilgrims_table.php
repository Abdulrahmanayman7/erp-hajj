<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stable tenant-level pilgrim identity. A pilgrim may be provisional
 * (no identity documents yet). No accommodation/transport state here.
 *
 * @see docs/09-modules/16-season-and-pilgrims/DATA_MODEL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pilgrims', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('full_name', 255);
            $table->string('gender', 16)->nullable();
            $table->string('nationality', 3)->nullable();
            $table->string('phone', 32)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'id'], 'pilgrims_tenant_id_id_unique');
            $table->index(['tenant_id', 'full_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pilgrims');
    }
};
