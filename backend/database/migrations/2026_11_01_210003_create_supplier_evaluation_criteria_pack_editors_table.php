<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S04 Phase II-C WP-01: SoD editor history for a criteria pack version (IIC-D1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_evaluation_criteria_pack_editors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('pack_id')
                ->constrained('supplier_evaluation_criteria_packs')
                ->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('first_edited_at');

            $table->unique(
                ['tenant_id', 'pack_id', 'user_id'],
                'sev_criteria_pack_editors_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_evaluation_criteria_pack_editors');
    }
};
