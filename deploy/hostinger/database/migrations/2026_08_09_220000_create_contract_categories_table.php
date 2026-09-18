<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant-owned contract categories catalog.
 *
 * @see docs/09-modules/05-contracts/DATA_MODEL.md
 * @see docs/10-decisions/ADR-0006-CONTRACT-CATEGORIES-CATALOG.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('name', 150);
            $table->string('code', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'name'], 'contract_categories_tenant_name_unique');
            $table->unique(['tenant_id', 'code'], 'contract_categories_tenant_code_unique');
            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_categories');
    }
};
