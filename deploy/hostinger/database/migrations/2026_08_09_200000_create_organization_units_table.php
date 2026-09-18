<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant-owned organization units (adjacency-list hierarchy).
 *
 * @see docs/09-modules/03-organization-structure/DATA_MODEL.md
 * @see docs/10-decisions/ADR-0004-ORGANIZATION-UNITS-HIERARCHY.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_units', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('organization_units')
                ->restrictOnDelete();
            $table->string('name', 150);
            $table->string('code', 50);
            $table->string('type', 20);
            $table->string('status', 20)->default('active');
            $table->foreignId('manager_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            // Sibling name uniqueness with NULL parents (MySQL UNIQUE treats NULLs specially).
            $table->unsignedBigInteger('parent_key')->storedAs('IFNULL(`parent_id`, 0)');

            $table->unique(['tenant_id', 'code'], 'organization_units_tenant_code_unique');
            $table->unique(['tenant_id', 'parent_key', 'name'], 'organization_units_tenant_parent_name_unique');
            $table->index(['tenant_id', 'parent_id']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'manager_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_units');
    }
};
