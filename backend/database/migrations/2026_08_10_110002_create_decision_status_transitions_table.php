<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only decision lifecycle history.
 *
 * @see docs/09-modules/07-decisions/DATA_MODEL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('decision_status_transitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('decision_id')->constrained('decisions')->cascadeOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('comment')->nullable();
            $table->string('correlation_id', 64);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'decision_id', 'created_at'], 'decision_transitions_tenant_decision_created_idx');
            $table->index(['decision_id', 'created_at'], 'decision_transitions_decision_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('decision_status_transitions');
    }
};
