<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S04 Phase II-C WP-02: immutable score snapshots captured at submit (IIC-D2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_evaluation_score_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('evaluation_id')->constrained('supplier_evaluations')->cascadeOnDelete();
            $table->unsignedBigInteger('source_criterion_id');
            $table->string('criterion_name');
            $table->decimal('weight', 8, 4);
            $table->decimal('score', 6, 2);
            $table->text('note')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'evaluation_id'], 'sev_score_snapshots_eval_idx');
            $table->unique(
                ['evaluation_id', 'source_criterion_id'],
                'sev_score_snapshots_criterion_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_evaluation_score_snapshots');
    }
};
