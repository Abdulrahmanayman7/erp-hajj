<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** S04 Phase III: working PR lines. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_request_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('purchase_request_id')->constrained('purchase_requests')->cascadeOnDelete();
            $table->unsignedInteger('line_no');
            $table->text('description');
            $table->text('specifications')->nullable();
            $table->decimal('quantity', 14, 4);
            $table->string('unit', 64);
            $table->date('need_date');
            $table->foreignId('inventory_item_id')->nullable()->constrained('inventory_items')->nullOnDelete();
            $table->decimal('unit_estimate', 14, 2)->nullable();
            $table->decimal('line_estimate', 14, 2)->nullable();
            $table->text('unpriced_reason')->nullable();
            $table->timestamps();

            $table->unique(['purchase_request_id', 'line_no'], 'pr_lines_line_no_unique');
            $table->index(['tenant_id', 'purchase_request_id'], 'pr_lines_pr_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_request_lines');
    }
};
