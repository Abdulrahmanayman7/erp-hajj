<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * First-class meeting recommendations (not Decisions).
 *
 * @see docs/09-modules/06-meetings/DATA_MODEL.md
 * @see docs/10-decisions/ADR-0007-MEETING-AGENDA-AND-RECOMMENDATIONS.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_recommendations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('meeting_id')->constrained('meetings')->restrictOnDelete();
            $table->foreignId('agenda_item_id')->nullable()->constrained('meeting_agenda_items')->nullOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft');
            $table->foreignId('owner_employee_id')->nullable()->constrained('employees')->restrictOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'meeting_id']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'owner_employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_recommendations');
    }
};
