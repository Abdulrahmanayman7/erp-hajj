<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S08 season reminder rules (Owner decisions S08-D11…D16, MATRIX 2026-11-08.2). Additive only.
 *
 * A rule is tenant + season scoped configuration. It never stores user / employee ids
 * (recipients are resolved at scan time from a permission, S08-D14) and has no fire
 * ledger: delivery idempotency is the existing notification dedupe key
 * `(tenant_id, dedupe_key)` built from the stable rule id (Control B).
 * `(tenant_id, season_id)` is a composite FK to `seasons (tenant_id, id)`.
 *
 * @see docs/09-modules/20-seasonal-templates/DATA_MODEL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('season_reminder_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants', indexName: 'sea_rem_tenant_fk')->restrictOnDelete();
            $table->unsignedBigInteger('season_id');
            $table->string('code', 64);
            $table->string('title', 255);
            $table->string('body', 1000)->nullable();
            $table->string('severity', 16);
            $table->string('status', 20)->default('draft');
            $table->string('trigger_mode', 32);
            $table->date('trigger_on')->nullable();
            $table->integer('offset_days')->nullable();
            $table->string('recipient_mode', 32)->default('permission_holders');
            $table->string('recipient_permission', 64);
            $table->foreignId('created_by')->constrained('users', indexName: 'sea_rem_created_by_fk')->restrictOnDelete();
            $table->timestamps();

            $table->foreign(['tenant_id', 'season_id'], 'sea_rem_season_fk')
                ->references(['tenant_id', 'id'])
                ->on('seasons')
                ->restrictOnDelete();

            $table->unique(['tenant_id', 'season_id', 'code'], 'sea_rem_tenant_season_code_uq');
            $table->index(['tenant_id', 'season_id', 'status'], 'sea_rem_tenant_season_status_idx');
            $table->index(['tenant_id', 'status', 'trigger_mode'], 'sea_rem_tenant_status_mode_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('season_reminder_rules');
    }
};
