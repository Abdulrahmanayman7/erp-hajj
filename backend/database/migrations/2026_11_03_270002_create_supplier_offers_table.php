<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** S05 Part 2: supplier offers against published RFQs. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_offers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('offer_number', 20);
            $table->foreignId('procurement_rfq_id')->constrained('procurement_rfqs')->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->string('status', 32);
            $table->string('currency', 3)->default('SAR');
            $table->text('notes')->nullable();
            $table->unsignedSmallInteger('lead_time_days')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('withdrawn_at')->nullable();
            $table->foreignId('withdrawn_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'offer_number'], 'supplier_offers_tenant_number_unique');
            $table->index(['tenant_id', 'procurement_rfq_id', 'status'], 'supplier_offers_tenant_rfq_status_idx');
            $table->index(['tenant_id', 'supplier_id'], 'supplier_offers_tenant_supplier_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_offers');
    }
};
