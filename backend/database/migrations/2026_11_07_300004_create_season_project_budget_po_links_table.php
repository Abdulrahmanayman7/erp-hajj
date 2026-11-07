<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Project budget ↔ S05 purchase order header links (E-Q06 = A).
 * Amount formulas deferred (E-Q07/E-Q08).
 *
 * @see docs/09-modules/20-season-projects/DATA_MODEL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('season_project_budget_po_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->unsignedBigInteger('season_id');
            $table->foreignId('project_id')->constrained('season_projects')->restrictOnDelete();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->restrictOnDelete();
            $table->decimal('commitment_amount', 18, 2)->nullable();
            $table->decimal('actual_amount', 18, 2)->nullable();
            $table->char('currency_code', 3)->default('SAR');
            $table->timestamp('linked_at');
            $table->foreignId('linked_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign(['tenant_id', 'season_id'], 'season_project_budget_po_links_season_fk')
                ->references(['tenant_id', 'id'])
                ->on('seasons')
                ->restrictOnDelete();

            $table->unique(['tenant_id', 'purchase_order_id'], 'season_project_budget_po_links_tenant_po_unique');
            $table->index(['tenant_id', 'project_id'], 'season_project_budget_po_links_tenant_project_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('season_project_budget_po_links');
    }
};
