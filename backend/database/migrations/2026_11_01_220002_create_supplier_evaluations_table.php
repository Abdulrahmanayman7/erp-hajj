<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S04 Phase II-C WP-02: tenant-owned supplier evaluations (versioned families).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_evaluations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('evaluation_number', 32);
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('supplier_category_id')->constrained('supplier_categories')->restrictOnDelete();
            $table->string('evaluation_kind', 32);
            $table->foreignId('season_id')->nullable()->constrained('seasons')->restrictOnDelete();
            $table->string('status', 32)->default('draft');
            $table->foreignId('criteria_pack_id')->constrained('supplier_evaluation_criteria_packs')->restrictOnDelete();
            $table->unsignedInteger('criteria_pack_version_no');
            $table->decimal('total_score', 6, 2)->nullable();
            $table->boolean('monitoring_recommended')->default(false);
            $table->foreignId('monitoring_set_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('monitoring_set_at')->nullable();
            $table->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->unsignedInteger('version_no')->default(1);
            $table->uuid('evaluation_family_key');
            $table->foreignId('supersedes_evaluation_id')->nullable()
                ->constrained('supplier_evaluations')
                ->nullOnDelete();
            // FK added after appeals table exists.
            $table->unsignedBigInteger('origin_appeal_id')->nullable();
            $table->json('draft_scores')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'evaluation_number'], 'sev_evaluations_number_unique');
            $table->unique(
                ['tenant_id', 'supplier_id', 'evaluation_family_key', 'version_no'],
                'sev_evaluations_family_version_unique',
            );
            $table->index(['tenant_id', 'status'], 'sev_evaluations_status_idx');
            $table->index(['tenant_id', 'supplier_id'], 'sev_evaluations_supplier_idx');
            $table->index(['tenant_id', 'supplier_category_id'], 'sev_evaluations_category_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_evaluations');
    }
};
