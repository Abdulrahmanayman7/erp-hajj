<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant-owned employees.
 *
 * @see docs/09-modules/04-employees-and-supervisors/DATA_MODEL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->string('employee_number', 32);
            $table->string('full_name', 150);
            $table->string('phone', 30)->nullable();
            $table->string('email', 255)->nullable();
            $table->foreignId('organization_unit_id')->constrained('organization_units')->restrictOnDelete();
            $table->foreignId('position_id')->nullable()->constrained('positions')->restrictOnDelete();
            $table->foreignId('supervisor_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('status', 20)->default('active');
            $table->date('hire_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'employee_number'], 'employees_tenant_number_unique');
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'organization_unit_id']);
            $table->index(['tenant_id', 'position_id']);
            $table->index(['tenant_id', 'supervisor_id']);
            $table->index(['tenant_id', 'full_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
