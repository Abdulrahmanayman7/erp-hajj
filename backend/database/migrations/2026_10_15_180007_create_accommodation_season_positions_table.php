<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stable positions enabled for one season unit. unit_id must be that season unit's unit.
 *
 * @see docs/09-modules/18-accommodation-and-camps/DATA_MODEL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accommodation_season_positions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants', indexName: 'acc_spos_tenant_fk')->restrictOnDelete();
            $table->unsignedBigInteger('season_id');
            $table->unsignedBigInteger('season_unit_id');
            $table->unsignedBigInteger('unit_id');
            $table->unsignedBigInteger('position_id');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'acc_spos_created_by_fk')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'id'], 'acc_spos_tenant_id_uq');
            $table->unique(['tenant_id', 'season_id', 'id'], 'acc_spos_tenant_season_id_uq');
            $table->unique(['tenant_id', 'season_id', 'id', 'season_unit_id'], 'acc_spos_season_row_unit_uq');
            $table->unique(['tenant_id', 'season_id', 'position_id'], 'acc_spos_once_per_season_uq');

            $table->foreign(['tenant_id', 'season_id', 'season_unit_id', 'unit_id'], 'acc_spos_sunit_fk')
                ->references(['tenant_id', 'season_id', 'id', 'unit_id'])
                ->on('accommodation_season_units')
                ->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id', 'position_id'], 'acc_spos_position_fk')
                ->references(['tenant_id', 'unit_id', 'id'])
                ->on('accommodation_positions')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accommodation_season_positions');
    }
};
