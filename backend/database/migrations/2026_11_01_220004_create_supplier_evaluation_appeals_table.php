<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S04 Phase II-C WP-03: evaluation appeals register (IIC-D4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_evaluation_appeals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('evaluation_id')->constrained('supplier_evaluations')->restrictOnDelete();
            $table->text('reason');
            $table->string('status', 16)->default('open');
            // 0 while open; appeal id otherwise — UNIQUE => at most one open appeal per evaluation.
            $table->unsignedBigInteger('open_slot')->default(0);
            $table->foreignId('filed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('filed_at');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_outcome_note')->nullable();
            $table->foreignId('resulting_evaluation_id')->nullable()
                ->constrained('supplier_evaluations')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'evaluation_id', 'open_slot'], 'sev_appeals_one_open_unique');
            $table->index(['tenant_id', 'status'], 'sev_appeals_status_idx');
        });

        Schema::table('supplier_evaluations', function (Blueprint $table): void {
            $table->foreign('origin_appeal_id', 'sev_evaluations_origin_appeal_fk')
                ->references('id')
                ->on('supplier_evaluation_appeals')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('supplier_evaluations', function (Blueprint $table): void {
            $table->dropForeign('sev_evaluations_origin_appeal_fk');
        });
        Schema::dropIfExists('supplier_evaluation_appeals');
    }
};
