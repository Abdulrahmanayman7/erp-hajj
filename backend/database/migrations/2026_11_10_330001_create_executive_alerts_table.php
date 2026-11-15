<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S10 A4 operational alerts (S10-D04=C).
 *
 * One row per (tenant, dedupe_key): the scanner upserts idempotently and flips the row
 * to `cleared` when the underlying condition is no longer true. Only the two rules that
 * have an approved formula exist (ALT-RISK-HIGH, ALT-PROC-OVERDUE); occupancy, planned
 * progress lag and complaint SLA rules are DEFERRED_BLOCKED (U01/U02/U03 = C).
 * `(tenant_id, season_id)` is a composite FK to `seasons (tenant_id, id)`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('executive_alerts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants', indexName: 'exec_alerts_tenant_fk')->restrictOnDelete();
            $table->unsignedBigInteger('season_id');
            $table->string('rule_code', 32);
            $table->string('dedupe_key', 191);
            $table->string('status', 16)->default('open');
            $table->string('title', 255);
            $table->string('body', 1000)->nullable();
            $table->string('severity', 16);
            $table->string('source_entity_type', 64)->nullable();
            $table->unsignedBigInteger('source_entity_id')->nullable();
            $table->json('meta')->nullable();
            // useCurrent(): MariaDB/MySQL reject a second NOT NULL TIMESTAMP without an
            // explicit default under NO_ZERO_DATE (implicit 0000-00-00). Both fields are
            // always written by the scanner on insert; CURRENT_TIMESTAMP is a safe default.
            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('last_seen_at')->useCurrent();
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users', indexName: 'exec_alerts_ack_by_fk')->nullOnDelete();
            $table->timestamp('cleared_at')->nullable();
            $table->timestamps();

            $table->foreign(['tenant_id', 'season_id'], 'exec_alerts_season_fk')
                ->references(['tenant_id', 'id'])
                ->on('seasons')
                ->restrictOnDelete();

            $table->unique(['tenant_id', 'dedupe_key'], 'exec_alerts_tenant_dedupe_uq');
            $table->index(['tenant_id', 'season_id', 'status'], 'exec_alerts_tenant_season_status_idx');
            $table->index(['tenant_id', 'rule_code', 'status'], 'exec_alerts_tenant_rule_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('executive_alerts');
    }
};
