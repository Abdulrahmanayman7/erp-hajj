<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S10 G1 hardening — authorized GPS device ↔ season-vehicle bindings.
 *
 * Ingest refuses any device_id that is not bound to the claimed
 * transport_season_vehicle_id within the same tenant (fail-closed).
 * Provisioning is out-of-band until a G4 vendor/admin surface exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gps_device_bindings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants', indexName: 'gps_bind_tenant_fk')->restrictOnDelete();
            $table->string('device_id', 64);
            $table->unsignedBigInteger('transport_season_vehicle_id');
            $table->timestamps();

            $table->foreign('transport_season_vehicle_id', 'gps_bind_tsv_fk')
                ->references('id')
                ->on('transport_season_vehicles')
                ->restrictOnDelete();

            $table->unique(['tenant_id', 'device_id'], 'gps_bind_tenant_device_uq');
            $table->unique(['tenant_id', 'transport_season_vehicle_id'], 'gps_bind_tenant_tsv_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gps_device_bindings');
    }
};
