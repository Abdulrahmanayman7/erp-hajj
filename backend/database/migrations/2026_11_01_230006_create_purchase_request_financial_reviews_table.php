<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** S04 Phase III: version-bound financial reviews (D2 / III-D2). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_request_financial_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('purchase_request_id')->constrained('purchase_requests')->restrictOnDelete();
            $table->unsignedInteger('version_no');
            $table->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
            $table->string('outcome', 32);
            $table->text('justification');
            $table->dateTime('reviewed_at');
            $table->timestamps();

            $table->index(['purchase_request_id', 'version_no', 'reviewed_at'], 'pr_fin_reviews_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_request_financial_reviews');
    }
};
