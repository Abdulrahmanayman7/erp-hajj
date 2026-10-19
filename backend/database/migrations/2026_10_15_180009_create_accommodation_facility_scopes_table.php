<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * User + season + facility grant. user_id references users.id only.
 * active_grant is 1 while revoked_at is null, else null, so one live grant
 * and many revoked rows can share the same user and facility.
 *
 * @see docs/09-modules/18-accommodation-and-camps/DATA_MODEL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accommodation_facility_scopes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants', indexName: 'acc_fsc_tenant_fk')->restrictOnDelete();
            $table->unsignedBigInteger('season_id');
            $table->unsignedBigInteger('facility_id');
            $table->foreignId('user_id')->constrained('users', indexName: 'acc_fsc_user_fk')->restrictOnDelete();
            $table->foreignId('granted_by')->nullable()->constrained('users', indexName: 'acc_fsc_granted_by_fk')->restrictOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->unsignedTinyInteger('active_grant')
                ->nullable()
                ->storedAs('case when revoked_at is null then 1 else null end');
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'season_id', 'user_id', 'facility_id', 'active_grant'],
                'acc_fsc_live_grant_uq',
            );
            $table->foreign(['tenant_id', 'season_id'], 'acc_fsc_season_fk')
                ->references(['tenant_id', 'id'])
                ->on('seasons')
                ->restrictOnDelete();
            $table->foreign(['tenant_id', 'facility_id'], 'acc_fsc_facility_fk')
                ->references(['tenant_id', 'id'])
                ->on('accommodation_facilities')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accommodation_facility_scopes');
    }
};
