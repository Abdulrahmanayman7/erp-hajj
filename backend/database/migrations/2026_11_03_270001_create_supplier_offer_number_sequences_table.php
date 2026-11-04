<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** S05 Part 2: per-tenant supplier offer number sequences. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_offer_number_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->unsignedBigInteger('next_value');
            $table->timestamps();

            $table->unique(['tenant_id'], 'supplier_offer_number_sequences_tenant_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_offer_number_sequences');
    }
};
