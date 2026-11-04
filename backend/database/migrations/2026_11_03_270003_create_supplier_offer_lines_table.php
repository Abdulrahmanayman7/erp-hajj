<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** S05 Part 2: supplier offer line prices. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_offer_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('supplier_offer_id')->constrained('supplier_offers')->cascadeOnDelete();
            $table->foreignId('procurement_rfq_line_id')->constrained('procurement_rfq_lines')->restrictOnDelete();
            $table->unsignedInteger('line_no');
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_price', 18, 4);
            $table->unsignedSmallInteger('lead_time_days')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'supplier_offer_id', 'line_no'], 'supplier_offer_lines_tenant_offer_line_unique');
            $table->unique(
                ['tenant_id', 'supplier_offer_id', 'procurement_rfq_line_id'],
                'supplier_offer_lines_tenant_offer_rfq_line_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_offer_lines');
    }
};
