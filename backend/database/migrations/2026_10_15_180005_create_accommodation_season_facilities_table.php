<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A stable facility selected for one season. Audience is optional and not enforced here.
 *
 * @see docs/09-modules/18-accommodation-and-camps/DATA_MODEL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accommodation_season_facilities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants', indexName: 'acc_sfac_tenant_fk')->restrictOnDelete();
            $table->unsignedBigInteger('season_id');
            $table->unsignedBigInteger('facility_id');
            $table->string('audience', 16)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'acc_sfac_created_by_fk')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'id'], 'acc_sfac_tenant_id_uq');
            $table->unique(['tenant_id', 'season_id', 'id'], 'acc_sfac_tenant_season_id_uq');
            $table->unique(['tenant_id', 'season_id', 'id', 'facility_id'], 'acc_sfac_tenant_season_fac_uq');
            $table->unique(['tenant_id', 'season_id', 'facility_id'], 'acc_sfac_once_per_season_uq');

            $table->foreign(['tenant_id', 'season_id'], 'acc_sfac_season_fk')
                ->references(['tenant_id', 'id'])
                ->on('seasons')
                ->restrictOnDelete();
            $table->foreign(['tenant_id', 'facility_id'], 'acc_sfac_facility_fk')
                ->references(['tenant_id', 'id'])
                ->on('accommodation_facilities')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accommodation_season_facilities');
    }
};
