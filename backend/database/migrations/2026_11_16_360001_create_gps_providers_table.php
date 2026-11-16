<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-provider GPS hub — tenant-owned provider configuration.
 *
 * Secrets are stored encrypted (Laravel encrypted cast). Live outbound to real
 * vendors remains gated (G4 Live BLOCKED). No GPS history table is created (G5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gps_providers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants', indexName: 'gps_prov_tenant_fk')->restrictOnDelete();
            $table->string('name', 120);
            $table->string('code', 64);
            $table->string('connection_method', 32);
            $table->boolean('is_enabled')->default(false);
            $table->json('settings_json')->nullable();
            $table->text('credentials_ciphertext')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->string('last_health_status', 32)->nullable();
            $table->string('last_error_sanitized', 500)->nullable();
            $table->unsignedInteger('poll_interval_seconds')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'code'], 'gps_prov_tenant_code_uq');
            $table->index(['tenant_id', 'connection_method', 'is_enabled'], 'gps_prov_tenant_method_en_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gps_providers');
    }
};
