<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** S04 Phase III: immutable version headers (III-D7). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_request_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('purchase_request_id')->constrained('purchase_requests')->restrictOnDelete();
            $table->unsignedInteger('version_no');
            $table->string('status', 32);
            $table->string('request_kind', 32);
            $table->unsignedBigInteger('season_id')->nullable();
            $table->string('title');
            $table->text('purpose');
            $table->string('priority', 16);
            $table->text('urgency_justification')->nullable();
            $table->unsignedBigInteger('requested_by');
            $table->unsignedBigInteger('beneficiary_organization_unit_id');
            $table->unsignedBigInteger('optional_supplier_id')->nullable();
            $table->char('currency', 3);
            $table->decimal('estimated_total_priced', 14, 2);
            $table->boolean('has_unpriced_lines');
            $table->timestamp('duplicate_warning_acknowledged_at')->nullable();
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('snapshot_at');
            $table->timestamps();

            $table->unique(['tenant_id', 'purchase_request_id', 'version_no'], 'pr_versions_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_request_versions');
    }
};
