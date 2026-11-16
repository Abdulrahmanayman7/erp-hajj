<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link device bindings to a configured GPS provider (nullable for legacy webhook ingest).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gps_device_bindings', function (Blueprint $table): void {
            $table->unsignedBigInteger('gps_provider_id')->nullable()->after('transport_season_vehicle_id');
            $table->string('external_device_id', 128)->nullable()->after('device_id');
            $table->boolean('is_active')->default(true)->after('gps_provider_id');

            $table->foreign('gps_provider_id', 'gps_bind_provider_fk')
                ->references('id')
                ->on('gps_providers')
                ->nullOnDelete();

            $table->index(['tenant_id', 'gps_provider_id'], 'gps_bind_tenant_provider_idx');
        });
    }

    public function down(): void
    {
        Schema::table('gps_device_bindings', function (Blueprint $table): void {
            $table->dropForeign('gps_bind_provider_fk');
            $table->dropIndex('gps_bind_tenant_provider_idx');
            $table->dropColumn(['gps_provider_id', 'external_device_id', 'is_active']);
        });
    }
};
