<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->restrictOnDelete();
            $table->string('context_type', 16);
            $table->string('actor_type', 16);
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_label', 255)->nullable();
            $table->string('event_type', 64);
            $table->string('entity_type', 64)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('entity_number', 64)->nullable();
            $table->string('entity_label', 255)->nullable();
            $table->string('reason', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->json('before_values')->nullable();
            $table->json('after_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->string('correlation_id', 64);
            $table->string('source', 16);
            $table->timestamp('created_at');

            $table->index(['tenant_id', 'created_at'], 'audit_logs_tenant_created_idx');
            $table->index(['tenant_id', 'event_type', 'created_at'], 'audit_logs_tenant_event_created_idx');
            $table->index(['tenant_id', 'actor_user_id', 'created_at'], 'audit_logs_tenant_actor_created_idx');
            $table->index(['tenant_id', 'entity_type', 'entity_id', 'created_at'], 'audit_logs_tenant_entity_created_idx');
            $table->index(['tenant_id', 'correlation_id'], 'audit_logs_tenant_correlation_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
