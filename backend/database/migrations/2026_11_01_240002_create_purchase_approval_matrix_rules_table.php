<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** S04 Phase IV WP01-A: approval matrix rules (bands, scope, unpriced path). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_approval_matrix_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('matrix_id')->constrained('purchase_approval_matrices')->restrictOnDelete();
            $table->decimal('min_amount', 14, 2)->nullable();
            $table->decimal('max_amount', 14, 2)->nullable();
            $table->string('request_kind', 32)->nullable();
            $table->foreignId('organization_unit_id')->nullable()->constrained('organization_units')->restrictOnDelete();
            $table->foreignId('season_id')->nullable()->constrained('seasons')->restrictOnDelete();
            $table->boolean('applies_when_unpriced')->default(false);
            $table->unsignedInteger('specificity_hint')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'matrix_id'], 'pamr_tenant_matrix_idx');
            $table->index(['matrix_id', 'applies_when_unpriced'], 'pamr_matrix_unpriced_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_approval_matrix_rules');
    }
};
