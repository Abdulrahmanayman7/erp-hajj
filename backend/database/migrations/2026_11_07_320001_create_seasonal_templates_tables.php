<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S08 Seasonal Templates & Safe Copy (tenant-owned, additive only).
 *
 * Domain target tables (season_programs, transport_*, accommodation_*) are NOT
 * altered: copy provenance lives in `season_copy_row_provenance`.
 *
 * @see docs/09-modules/20-seasonal-templates/DATA_MODEL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('season_template_number_sequences', function (Blueprint $table): void {
            $table->foreignId('tenant_id')->primary()->constrained('tenants', indexName: 'sea_tpl_seq_tenant_fk')->restrictOnDelete();
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();
        });

        Schema::create('season_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants', indexName: 'sea_tpl_tenant_fk')->restrictOnDelete();
            $table->string('template_number', 32);
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->string('status', 32);
            $table->string('matrix_version', 32);
            $table->foreignId('created_by')->constrained('users', indexName: 'sea_tpl_created_by_fk')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users', indexName: 'sea_tpl_approved_by_fk')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'id'], 'sea_tpl_tenant_id_uq');
            $table->unique(['tenant_id', 'template_number'], 'sea_tpl_tenant_number_uq');
            $table->index(['tenant_id', 'status'], 'sea_tpl_tenant_status_idx');
        });

        Schema::create('season_template_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants', indexName: 'sea_tpl_item_tenant_fk')->restrictOnDelete();
            $table->unsignedBigInteger('season_template_id');
            $table->string('entity_kind', 64);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->json('selection_json')->nullable();
            $table->timestamps();

            $table->foreign(['tenant_id', 'season_template_id'], 'sea_tpl_item_template_fk')
                ->references(['tenant_id', 'id'])
                ->on('season_templates')
                ->restrictOnDelete();

            $table->unique(['season_template_id', 'entity_kind'], 'sea_tpl_item_kind_uq');
        });

        Schema::create('season_copy_previews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants', indexName: 'sea_cpv_tenant_fk')->restrictOnDelete();
            $table->uuid('preview_token');
            $table->string('mode', 32);
            $table->unsignedBigInteger('source_season_id');
            $table->unsignedBigInteger('source_template_id')->nullable();
            $table->unsignedBigInteger('target_season_id');
            $table->string('matrix_version', 32);
            $table->char('content_hash', 64);
            $table->char('source_fingerprint', 64);
            $table->char('target_fingerprint', 64);
            $table->json('adapter_fingerprints_json');
            $table->json('date_mapping_json')->nullable();
            $table->json('selection_json');
            $table->json('counts_json');
            $table->string('status', 32);
            $table->timestamp('expires_at');
            $table->string('invalidated_reason', 64)->nullable();
            $table->foreignId('created_by')->constrained('users', indexName: 'sea_cpv_created_by_fk')->restrictOnDelete();
            $table->timestamps();

            $table->foreign(['tenant_id', 'source_season_id'], 'sea_cpv_source_season_fk')
                ->references(['tenant_id', 'id'])
                ->on('seasons')
                ->restrictOnDelete();
            $table->foreign(['tenant_id', 'target_season_id'], 'sea_cpv_target_season_fk')
                ->references(['tenant_id', 'id'])
                ->on('seasons')
                ->restrictOnDelete();
            $table->foreign(['tenant_id', 'source_template_id'], 'sea_cpv_template_fk')
                ->references(['tenant_id', 'id'])
                ->on('season_templates')
                ->restrictOnDelete();

            $table->unique(['tenant_id', 'id'], 'sea_cpv_tenant_id_uq');
            $table->unique(['tenant_id', 'preview_token'], 'sea_cpv_tenant_token_uq');
            $table->index(['tenant_id', 'target_season_id', 'status'], 'sea_cpv_target_status_idx');
            $table->index(['tenant_id', 'expires_at'], 'sea_cpv_expires_idx');
        });

        Schema::create('season_copy_preview_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants', indexName: 'sea_cpl_tenant_fk')->restrictOnDelete();
            $table->unsignedBigInteger('preview_id');
            $table->string('entity_kind', 64);
            $table->string('action', 16);
            $table->string('natural_key', 255);
            $table->json('summary_json')->nullable();
            $table->string('conflict_code', 64)->nullable();
            $table->string('proposed_resolution', 32)->nullable();
            $table->timestamps();

            $table->foreign(['tenant_id', 'preview_id'], 'sea_cpl_preview_fk')
                ->references(['tenant_id', 'id'])
                ->on('season_copy_previews')
                ->restrictOnDelete();

            $table->index(['preview_id', 'entity_kind', 'action'], 'sea_cpl_preview_kind_idx');
        });

        Schema::create('season_copy_run_number_sequences', function (Blueprint $table): void {
            $table->foreignId('tenant_id')->primary()->constrained('tenants', indexName: 'sea_crun_seq_tenant_fk')->restrictOnDelete();
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();
        });

        Schema::create('season_copy_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants', indexName: 'sea_crun_tenant_fk')->restrictOnDelete();
            $table->string('run_number', 32);
            $table->unsignedBigInteger('preview_id');
            $table->string('mode', 32);
            $table->unsignedBigInteger('source_season_id');
            $table->unsignedBigInteger('source_template_id')->nullable();
            $table->unsignedBigInteger('target_season_id');
            $table->string('status', 32);
            $table->string('error_code', 64)->nullable();
            $table->string('error_message', 255)->nullable();
            $table->json('counts_json')->nullable();
            $table->string('correlation_id', 64);
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->foreignId('created_by')->constrained('users', indexName: 'sea_crun_created_by_fk')->restrictOnDelete();
            $table->timestamps();

            $table->foreign(['tenant_id', 'preview_id'], 'sea_crun_preview_fk')
                ->references(['tenant_id', 'id'])
                ->on('season_copy_previews')
                ->restrictOnDelete();
            $table->foreign(['tenant_id', 'source_season_id'], 'sea_crun_source_season_fk')
                ->references(['tenant_id', 'id'])
                ->on('seasons')
                ->restrictOnDelete();
            $table->foreign(['tenant_id', 'target_season_id'], 'sea_crun_target_season_fk')
                ->references(['tenant_id', 'id'])
                ->on('seasons')
                ->restrictOnDelete();
            $table->foreign(['tenant_id', 'source_template_id'], 'sea_crun_template_fk')
                ->references(['tenant_id', 'id'])
                ->on('season_templates')
                ->restrictOnDelete();

            $table->unique(['tenant_id', 'id'], 'sea_crun_tenant_id_uq');
            $table->unique(['tenant_id', 'run_number'], 'sea_crun_tenant_number_uq');
            $table->index(['tenant_id', 'created_at'], 'sea_crun_created_idx');
            $table->index(['tenant_id', 'target_season_id'], 'sea_crun_target_idx');
        });

        Schema::create('season_copy_run_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants', indexName: 'sea_crl_tenant_fk')->restrictOnDelete();
            $table->unsignedBigInteger('run_id');
            $table->string('entity_kind', 64);
            $table->string('natural_key', 255);
            $table->string('result', 32);
            $table->string('reason_code', 64)->nullable();
            $table->unsignedBigInteger('source_row_id')->nullable();
            $table->unsignedBigInteger('target_row_id')->nullable();
            $table->timestamps();

            $table->foreign(['tenant_id', 'run_id'], 'sea_crl_run_fk')
                ->references(['tenant_id', 'id'])
                ->on('season_copy_runs')
                ->restrictOnDelete();

            $table->index(['run_id', 'entity_kind'], 'sea_crl_run_kind_idx');
        });

        Schema::create('season_copy_row_provenance', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants', indexName: 'sea_prov_tenant_fk')->restrictOnDelete();
            $table->unsignedBigInteger('target_season_id');
            $table->string('entity_kind', 64);
            $table->unsignedBigInteger('target_row_id');
            $table->unsignedBigInteger('source_season_id');
            $table->unsignedBigInteger('source_template_id')->nullable();
            $table->unsignedBigInteger('source_row_id')->nullable();
            $table->unsignedBigInteger('copy_run_id');
            $table->boolean('system_owned')->default(true);
            $table->char('copied_hash', 64);
            $table->timestamps();

            $table->foreign(['tenant_id', 'target_season_id'], 'sea_prov_target_season_fk')
                ->references(['tenant_id', 'id'])
                ->on('seasons')
                ->restrictOnDelete();
            $table->foreign(['tenant_id', 'source_season_id'], 'sea_prov_source_season_fk')
                ->references(['tenant_id', 'id'])
                ->on('seasons')
                ->restrictOnDelete();
            $table->foreign(['tenant_id', 'copy_run_id'], 'sea_prov_run_fk')
                ->references(['tenant_id', 'id'])
                ->on('season_copy_runs')
                ->restrictOnDelete();

            $table->unique(['tenant_id', 'entity_kind', 'target_row_id'], 'sea_prov_target_row_uq');
            $table->index(['tenant_id', 'target_season_id', 'entity_kind'], 'sea_prov_target_kind_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('season_copy_row_provenance');
        Schema::dropIfExists('season_copy_run_lines');
        Schema::dropIfExists('season_copy_runs');
        Schema::dropIfExists('season_copy_run_number_sequences');
        Schema::dropIfExists('season_copy_preview_lines');
        Schema::dropIfExists('season_copy_previews');
        Schema::dropIfExists('season_template_items');
        Schema::dropIfExists('season_templates');
        Schema::dropIfExists('season_template_number_sequences');
    }
};
