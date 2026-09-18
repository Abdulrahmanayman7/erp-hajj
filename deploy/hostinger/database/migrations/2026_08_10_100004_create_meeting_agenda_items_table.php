<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Structured meeting agenda items.
 *
 * @see docs/09-modules/06-meetings/DATA_MODEL.md
 * @see docs/10-decisions/ADR-0007-MEETING-AGENDA-AND-RECOMMENDATIONS.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_agenda_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('meeting_id')->constrained('meetings')->restrictOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'meeting_id', 'sort_order'], 'meeting_agenda_tenant_meeting_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_agenda_items');
    }
};
