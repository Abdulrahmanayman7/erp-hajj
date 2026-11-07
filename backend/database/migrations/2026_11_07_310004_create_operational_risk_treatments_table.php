<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Current treatment plan for an operational risk (S07 D11).
 *
 * @see docs/09-modules/21-risks-and-complaints/DATA_MODEL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_risk_treatments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('risk_id')->constrained('operational_risks')->restrictOnDelete();
            $table->string('title', 200);
            $table->text('plan_body');
            $table->foreignId('owner_employee_id')->constrained('employees')->restrictOnDelete();
            $table->timestamp('due_at')->nullable();
            $table->string('status', 32);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique('risk_id', 'operational_risk_treatments_risk_uq');
            $table->index(['tenant_id', 'status'], 'operational_risk_treatments_tenant_status_idx');

            $table->foreign(['tenant_id', 'risk_id'], 'operational_risk_treatments_risk_tenant_fk')
                ->references(['tenant_id', 'id'])
                ->on('operational_risks')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_risk_treatments');
    }
};
