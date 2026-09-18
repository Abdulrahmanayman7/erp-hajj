<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only meeting lifecycle history.
 *
 * @see docs/09-modules/06-meetings/DATA_MODEL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_status_transitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('meeting_id')->constrained('meetings')->restrictOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('comment')->nullable();
            $table->string('correlation_id', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'meeting_id', 'created_at'], 'meeting_transitions_tenant_meeting_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_status_transitions');
    }
};
