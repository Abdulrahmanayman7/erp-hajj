<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Durable notification occurrence identities for S07 (ADR-0013 / N01).
 * Each domain notification trigger inserts one row in the same transaction;
 * the row id is the stable occurrence key for NotificationDedupe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_notification_occurrences', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('entity_type', 64);
            $table->unsignedBigInteger('entity_id');
            $table->string('event_kind', 64);
            $table->timestamp('created_at')->useCurrent();

            $table->index(
                ['tenant_id', 'entity_type', 'entity_id', 'event_kind'],
                'op_notif_occ_tenant_entity_kind_idx',
            );
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_notification_occurrences');
    }
};
