<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Meeting attendees (tenant employees only).
 *
 * @see docs/09-modules/06-meetings/DATA_MODEL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_attendees', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('meeting_id')->constrained('meetings')->restrictOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->string('attendance_status', 20)->default('invited');
            $table->timestamps();

            $table->unique(['meeting_id', 'employee_id'], 'meeting_attendees_meeting_employee_unique');
            $table->index(['tenant_id', 'meeting_id']);
            $table->index(['tenant_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_attendees');
    }
};
