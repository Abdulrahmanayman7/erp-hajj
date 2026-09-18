<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant-owned in-app notifications (recipient = User).
 *
 * @see docs/09-modules/12-notifications/DATA_MODEL.md
 * @see docs/10-decisions/ADR-0013-IN-APP-NOTIFICATION-OWNERSHIP-AND-DELIVERY.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('recipient_user_id')->constrained('users')->restrictOnDelete();
            $table->string('type', 64);
            $table->string('title', 255);
            $table->string('body', 1000)->nullable();
            $table->string('severity', 16);
            $table->string('entity_type', 64)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('dedupe_key', 191)->nullable();
            $table->timestamp('read_at')->nullable();
            $table->string('correlation_id', 64)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'dedupe_key'], 'notifications_tenant_dedupe_unique');
            $table->index(['tenant_id', 'recipient_user_id', 'read_at'], 'notifications_tenant_recipient_read_idx');
            $table->index(['tenant_id', 'recipient_user_id', 'created_at'], 'notifications_tenant_recipient_created_idx');
            $table->index(['tenant_id', 'type'], 'notifications_tenant_type_idx');
            $table->index(['tenant_id', 'entity_type', 'entity_id'], 'notifications_tenant_entity_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
