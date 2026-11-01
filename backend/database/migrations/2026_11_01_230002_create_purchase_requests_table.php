<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** S04 Phase III: purchase request headers (III-D1–D8). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('pr_number', 32);
            $table->unsignedInteger('version_no')->default(1);
            $table->string('status', 32)->default('draft');
            $table->string('request_kind', 32);
            $table->foreignId('season_id')->nullable()->constrained('seasons')->restrictOnDelete();
            $table->string('title');
            $table->text('purpose');
            $table->string('priority', 16)->default('normal');
            $table->text('urgency_justification')->nullable();
            $table->timestamp('priority_verified_at')->nullable();
            $table->foreignId('priority_verified_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('beneficiary_organization_unit_id')->constrained('organization_units')->restrictOnDelete();
            $table->foreignId('optional_supplier_id')->nullable()->constrained('suppliers')->restrictOnDelete();
            $table->char('currency', 3)->default('SAR');
            $table->decimal('estimated_total_priced', 14, 2)->default(0);
            $table->boolean('has_unpriced_lines')->default(false);
            $table->unsignedBigInteger('matrix_id')->nullable();
            $table->unsignedInteger('matrix_version_no')->nullable();
            $table->timestamp('duplicate_warning_acknowledged_at')->nullable();
            $table->timestamp('procurement_engaged_at')->nullable();
            $table->string('procurement_engagement_ref', 64)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'pr_number'], 'pr_number_unique');
            $table->index(['tenant_id', 'status'], 'pr_status_idx');
            $table->index(['tenant_id', 'season_id'], 'pr_season_idx');
            $table->index(['tenant_id', 'requested_by'], 'pr_requester_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_requests');
    }
};
