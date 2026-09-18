<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant-owned contracts.
 *
 * @see docs/09-modules/05-contracts/DATA_MODEL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('contract_number', 32);
            $table->string('title', 200);
            $table->foreignId('contract_category_id')->constrained('contract_categories')->restrictOnDelete();
            $table->string('status', 20)->default('draft');
            $table->string('counterparty_name', 200);
            $table->string('counterparty_kind', 20)->default('organization');
            $table->foreignId('employee_id')->nullable()->constrained('employees')->restrictOnDelete();
            $table->foreignId('organization_unit_id')->nullable()->constrained('organization_units')->restrictOnDelete();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->decimal('value', 15, 2)->nullable();
            $table->char('currency', 3)->default('SAR');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('renewed_from_contract_id')->nullable()->constrained('contracts')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'contract_number'], 'contracts_tenant_number_unique');
            $table->unique('renewed_from_contract_id', 'contracts_renewed_from_unique');
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'contract_category_id']);
            $table->index(['tenant_id', 'employee_id']);
            $table->index(['tenant_id', 'organization_unit_id']);
            $table->index(['tenant_id', 'end_date']);
            $table->index(['tenant_id', 'start_date']);
            $table->index(['tenant_id', 'title']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
