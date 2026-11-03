<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** S05 Part 4: GRN number sequences (GRN-######). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_receipt_number_sequences', function (Blueprint $table): void {
            $table->unsignedBigInteger('tenant_id')->primary();
            $table->unsignedBigInteger('next_value')->default(1);
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_number_sequences');
    }
};
