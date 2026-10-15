<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Seasonal identity snapshot (F6, F19, F20): an explicit allow-list of
 * identity values captured for one SeasonPilgrim. Refreshable while
 * Registered, locked on activation, corrected only via an explicit audited
 * action afterwards.
 *
 * (tenant_id, season_pilgrim_id) → season_pilgrims (tenant_id, id) makes a
 * snapshot whose tenant differs from its participation impossible.
 *
 * pilgrim_identity_document_id is provenance only (the captured values are
 * the record) and is nulled when the document is deleted. A composite FK
 * cannot be used here: ON DELETE SET NULL on (tenant_id, document_id) would
 * null the NOT NULL tenant_id. Same-tenant / same-pilgrim provenance is
 * enforced by the capturing action.
 *
 * @see docs/09-modules/16-season-and-pilgrims/DATA_MODEL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('season_pilgrim_identity_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->unsignedBigInteger('season_pilgrim_id')->unique('sp_identity_snapshots_season_pilgrim_unique');
            $table->unsignedBigInteger('pilgrim_identity_document_id')->nullable();
            $table->string('full_name', 255);
            $table->string('gender', 16)->nullable();
            $table->string('nationality', 3)->nullable();
            $table->string('document_type', 32)->nullable();
            $table->string('document_issuing_country', 3)->nullable();
            $table->string('document_number', 64)->nullable();
            $table->timestamp('captured_at');
            $table->foreignId('captured_by')->nullable()->constrained('users', indexName: 'sp_identity_snapshots_captured_by_fk')->restrictOnDelete();
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users', indexName: 'sp_identity_snapshots_locked_by_fk')->restrictOnDelete();
            $table->timestamp('corrected_at')->nullable();
            $table->foreignId('corrected_by')->nullable()->constrained('users', indexName: 'sp_identity_snapshots_corrected_by_fk')->restrictOnDelete();
            $table->unsignedInteger('correction_count')->default(0);
            $table->timestamps();

            $table->foreign(['tenant_id', 'season_pilgrim_id'], 'sp_identity_snapshots_season_pilgrim_fk')
                ->references(['tenant_id', 'id'])
                ->on('season_pilgrims')
                ->restrictOnDelete();
            $table->foreign('pilgrim_identity_document_id', 'sp_identity_snapshots_document_fk')
                ->references('id')
                ->on('pilgrim_identity_documents')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('season_pilgrim_identity_snapshots');
    }
};
