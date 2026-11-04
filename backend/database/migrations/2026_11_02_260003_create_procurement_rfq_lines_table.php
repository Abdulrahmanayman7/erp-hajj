<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** S05 Part 1: procurement RFQ lines. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_rfq_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('procurement_rfq_id')->constrained('procurement_rfqs')->cascadeOnDelete();
            $table->unsignedInteger('line_no');
            $table->foreignId('purchase_request_line_id')->nullable()->constrained('purchase_request_lines')->nullOnDelete();
            $table->string('description', 500);
            $table->text('specifications')->nullable();
            $table->decimal('quantity', 18, 4);
            $table->string('unit', 64);
            $table->date('need_date')->nullable();
            $table->foreignId('inventory_item_id')->nullable()->constrained('inventory_items')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'procurement_rfq_id', 'line_no'], 'procurement_rfq_lines_tenant_rfq_line_unique');
            $table->index(['tenant_id', 'procurement_rfq_id'], 'procurement_rfq_lines_tenant_rfq_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_rfq_lines');
    }
};
