<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** S04 Phase IV WP01-A: financial authority ceilings (covers_unpriced). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_approver_authorities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->decimal('max_amount', 14, 2);
            $table->char('currency', 3)->default('SAR');
            $table->boolean('covers_unpriced')->default(false);
            $table->foreignId('organization_unit_id')->nullable()->constrained('organization_units')->restrictOnDelete();
            $table->timestamp('effective_from');
            $table->timestamp('effective_to')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'user_id'], 'paa_tenant_user_idx');
            $table->index(['tenant_id', 'revoked_at', 'effective_to'], 'paa_tenant_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_approver_authorities');
    }
};
