<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** S04 Phase IV WP01-A: approval matrices (draft/published/retired). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_approval_matrices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('name');
            $table->string('status', 32)->default('draft');
            $table->unsignedInteger('version_no')->default(0);
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'status'], 'pam_tenant_status_idx');
            $table->index(['tenant_id', 'version_no'], 'pam_tenant_version_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_approval_matrices');
    }
};
