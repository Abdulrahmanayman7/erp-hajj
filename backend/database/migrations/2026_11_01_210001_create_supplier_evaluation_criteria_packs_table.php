<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S04 Phase II-C WP-01: versioned evaluation criteria packs per supplier category.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_evaluation_criteria_packs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('supplier_category_id')->constrained('supplier_categories')->restrictOnDelete();
            $table->unsignedInteger('version_no');
            $table->string('status', 16)->default('draft');
            // 0 while draft; version_no otherwise — UNIQUE => at most one draft per category.
            $table->unsignedInteger('draft_slot')->default(0);
            // 1 while published; NULL otherwise — UNIQUE => at most one published pack per category.
            $table->unsignedTinyInteger('published_slot')->nullable();
            $table->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('last_edited_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('supersedes_pack_id')->nullable()
                ->constrained('supplier_evaluation_criteria_packs')
                ->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'supplier_category_id', 'version_no'],
                'sev_criteria_packs_version_unique',
            );
            $table->unique(
                ['tenant_id', 'supplier_category_id', 'draft_slot'],
                'sev_criteria_packs_one_draft_unique',
            );
            $table->unique(
                ['tenant_id', 'supplier_category_id', 'published_slot'],
                'sev_criteria_packs_one_published_unique',
            );
            $table->index(['tenant_id', 'status'], 'sev_criteria_packs_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_evaluation_criteria_packs');
    }
};
