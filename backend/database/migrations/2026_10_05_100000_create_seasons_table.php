<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant Hajj seasons (draft → active → closed).
 *
 * `active_flag` is a generated column (1 when active, NULL otherwise) so that
 * UNIQUE (tenant_id, active_flag) enforces "at most one Active season per
 * tenant" on both MySQL and SQLite without partial indexes.
 *
 * @see docs/09-modules/16-season-and-pilgrims/DATA_MODEL.md
 * @see docs/10-decisions/ADR-0017-SEASON-PILGRIM-AND-PHASE2-BOUNDED-CONTEXTS.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seasons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('code', 40);
            $table->string('name', 255);
            $table->unsignedSmallInteger('hajj_year');
            $table->date('operational_starts_on');
            $table->date('operational_ends_on');
            $table->string('status', 32);
            $table->unsignedTinyInteger('active_flag')
                ->nullable()
                ->storedAs("case when status = 'active' then 1 else null end");
            $table->text('notes')->nullable();
            $table->timestamp('first_activated_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->foreignId('activated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reopened_at')->nullable();
            $table->foreignId('reopened_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'id'], 'seasons_tenant_id_id_unique');
            $table->unique(['tenant_id', 'code'], 'seasons_tenant_code_unique');
            $table->unique(['tenant_id', 'hajj_year'], 'seasons_tenant_hajj_year_unique');
            $table->unique(['tenant_id', 'active_flag'], 'seasons_tenant_single_active_unique');
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seasons');
    }
};
