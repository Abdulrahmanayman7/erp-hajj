<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enrich last-known cache for multi-provider hub (still NOT a history table — G5 BLOCKED).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gps_vehicle_last_known', function (Blueprint $table): void {
            $table->unsignedBigInteger('gps_provider_id')->nullable()->after('device_id');
            $table->string('provider_code', 64)->nullable()->after('gps_provider_id');
            $table->string('source', 32)->nullable()->after('provider_code');
            $table->decimal('speed_kph', 8, 2)->nullable()->after('source');
            $table->decimal('heading_deg', 6, 2)->nullable()->after('speed_kph');
            $table->decimal('accuracy_m', 8, 2)->nullable()->after('heading_deg');
            $table->boolean('is_simulated')->default(false)->after('accuracy_m');
            $table->timestamp('received_at')->nullable()->after('is_simulated');

            $table->foreign('gps_provider_id', 'gps_lkn_provider_fk')
                ->references('id')
                ->on('gps_providers')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('gps_vehicle_last_known', function (Blueprint $table): void {
            $table->dropForeign('gps_lkn_provider_fk');
            $table->dropColumn([
                'gps_provider_id',
                'provider_code',
                'source',
                'speed_kph',
                'heading_deg',
                'accuracy_m',
                'is_simulated',
                'received_at',
            ]);
        });
    }
};
