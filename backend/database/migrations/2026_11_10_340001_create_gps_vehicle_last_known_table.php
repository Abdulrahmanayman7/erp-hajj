<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S10 G1 — last-known GPS position per season vehicle.
 *
 * History / retention table is intentionally NOT created here (U04 / G5 = BLOCKED).
 * Only the latest reading is stored and upserted on ingest.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gps_vehicle_last_known', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants', indexName: 'gps_lkn_tenant_fk')->restrictOnDelete();
            $table->unsignedBigInteger('season_id');
            $table->unsignedBigInteger('transport_season_vehicle_id');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->timestamp('recorded_at');
            $table->string('device_id', 64)->nullable();
            $table->timestamps();

            $table->foreign(['tenant_id', 'season_id'], 'gps_lkn_season_fk')
                ->references(['tenant_id', 'id'])
                ->on('seasons')
                ->restrictOnDelete();

            // transport_season_vehicles has no (tenant_id, id) unique key; id PK + app tenant scope.
            $table->foreign('transport_season_vehicle_id', 'gps_lkn_tsv_fk')
                ->references('id')
                ->on('transport_season_vehicles')
                ->restrictOnDelete();

            $table->unique(['tenant_id', 'transport_season_vehicle_id'], 'gps_lkn_tenant_tsv_uq');
            $table->index(['tenant_id', 'season_id'], 'gps_lkn_tenant_season_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gps_vehicle_last_known');
    }
};
