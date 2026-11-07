<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only follow-up notes on operational complaints (S07).
 *
 * @see docs/09-modules/21-risks-and-complaints/DATA_MODEL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_complaint_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('complaint_id')->constrained('operational_complaints')->restrictOnDelete();
            $table->text('body');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'complaint_id'], 'operational_complaint_notes_tenant_complaint_idx');

            $table->foreign(['tenant_id', 'complaint_id'], 'operational_complaint_notes_complaint_tenant_fk')
                ->references(['tenant_id', 'id'])
                ->on('operational_complaints')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_complaint_notes');
    }
};
