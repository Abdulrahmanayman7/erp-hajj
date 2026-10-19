<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A stable unit selected for one season. capacity is stored and not enforced.
 * facility_id must match both the season facility and the unit.
 *
 * @see docs/09-modules/18-accommodation-and-camps/DATA_MODEL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accommodation_season_units', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants', indexName: 'acc_sunit_tenant_fk')->restrictOnDelete();
            $table->unsignedBigInteger('season_id');
            $table->unsignedBigInteger('season_facility_id');
            $table->unsignedBigInteger('facility_id');
            $table->unsignedBigInteger('unit_id');
            $table->string('allocation_mode', 32);
            $table->unsignedInteger('capacity')->nullable();
            $table->string('audience', 16);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'acc_sunit_created_by_fk')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'id'], 'acc_sunit_tenant_id_uq');
            $table->unique(['tenant_id', 'season_id', 'id'], 'acc_sunit_tenant_season_id_uq');
            $table->unique(['tenant_id', 'season_id', 'id', 'unit_id'], 'acc_sunit_tenant_season_unit_uq');
            $table->unique(['tenant_id', 'season_id', 'unit_id'], 'acc_sunit_once_per_season_uq');

            $table->foreign(['tenant_id', 'season_id'], 'acc_sunit_season_fk')
                ->references(['tenant_id', 'id'])
                ->on('seasons')
                ->restrictOnDelete();
            $table->foreign(['tenant_id', 'season_id', 'season_facility_id', 'facility_id'], 'acc_sunit_sfac_fk')
                ->references(['tenant_id', 'season_id', 'id', 'facility_id'])
                ->on('accommodation_season_facilities')
                ->restrictOnDelete();
            $table->foreign(['tenant_id', 'facility_id', 'unit_id'], 'acc_sunit_unit_fk')
                ->references(['tenant_id', 'facility_id', 'id'])
                ->on('accommodation_units')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accommodation_season_units');
    }
};
