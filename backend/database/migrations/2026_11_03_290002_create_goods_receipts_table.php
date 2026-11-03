<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** S05 Part 4: goods receipt headers. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('grn_number', 20);
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->restrictOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->restrictOnDelete();
            $table->string('status', 32);
            $table->string('idempotency_key', 64)->nullable();
            $table->string('correlation_id', 64)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'grn_number'], 'goods_receipts_tenant_number_unique');
            $table->unique(['tenant_id', 'idempotency_key'], 'goods_receipts_tenant_idempotency_unique');
            $table->index(['tenant_id', 'status'], 'goods_receipts_tenant_status_idx');
            $table->index(['tenant_id', 'purchase_order_id'], 'goods_receipts_tenant_po_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipts');
    }
};
