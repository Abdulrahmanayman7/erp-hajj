<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** S04 Phase IV WP01-A: ordered approval levels (pool-based; no assignee). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_approval_matrix_levels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('matrix_id')->constrained('purchase_approval_matrices')->restrictOnDelete();
            $table->unsignedInteger('level_order');
            $table->string('name');
            $table->timestamps();

            $table->unique(['matrix_id', 'level_order'], 'paml_matrix_order_unique');
            $table->index(['tenant_id', 'matrix_id'], 'paml_tenant_matrix_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_approval_matrix_levels');
    }
};
