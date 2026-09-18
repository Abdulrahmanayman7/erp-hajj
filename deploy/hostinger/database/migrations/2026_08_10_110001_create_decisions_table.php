<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant-owned governance decisions.
 *
 * @see docs/09-modules/07-decisions/DATA_MODEL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('decisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('decision_number', 20);
            $table->string('title', 255);
            $table->text('body');
            $table->text('notes')->nullable();
            $table->string('status', 32)->default('draft');
            $table->foreignId('source_recommendation_id')->nullable()->constrained('meeting_recommendations')->restrictOnDelete();
            $table->foreignId('organization_unit_id')->nullable()->constrained('organization_units')->restrictOnDelete();
            $table->foreignId('issued_by_employee_id')->nullable()->constrained('employees')->restrictOnDelete();
            $table->foreignId('responsible_employee_id')->nullable()->constrained('employees')->restrictOnDelete();
            $table->date('effective_date')->nullable();
            $table->date('due_date')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'decision_number'], 'decisions_tenant_number_unique');
            $table->unique('source_recommendation_id', 'decisions_source_recommendation_unique');
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'organization_unit_id']);
            $table->index(['tenant_id', 'responsible_employee_id']);
            $table->index(['tenant_id', 'effective_date']);
            $table->index(['tenant_id', 'due_date']);
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('decisions');
    }
};
