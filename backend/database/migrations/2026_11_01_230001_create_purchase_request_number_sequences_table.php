<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** S04 Phase III: PR number sequences (PR-######). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_request_number_sequences', function (Blueprint $table): void {
            $table->unsignedBigInteger('tenant_id')->primary();
            $table->unsignedBigInteger('next_value')->default(1);
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_request_number_sequences');
    }
};
