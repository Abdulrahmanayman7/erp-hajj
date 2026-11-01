<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S04 Phase II-C WP-01: weighted criteria lines belonging to a pack version.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_evaluation_criteria', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('pack_id')
                ->constrained('supplier_evaluation_criteria_packs')
                ->cascadeOnDelete();
            $table->string('name', 150);
            $table->decimal('weight', 8, 4);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'pack_id'], 'sev_criteria_pack_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_evaluation_criteria');
    }
};
