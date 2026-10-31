<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('supplier_number', 32);
            $table->string('status', 32)->default('draft');
            $table->string('supplier_type', 32);
            $table->string('display_name', 200);
            $table->string('legal_name', 200)->nullable();
            $table->char('country_code', 2);
            $table->string('legal_id_type', 32)->nullable();
            $table->string('legal_id_value', 64)->nullable();
            $table->string('legal_id_normalized', 64)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'supplier_number'], 'suppliers_tenant_number_unique');
            $table->unique(['tenant_id', 'legal_id_type', 'legal_id_normalized'], 'suppliers_tenant_legal_id_unique');
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'display_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
