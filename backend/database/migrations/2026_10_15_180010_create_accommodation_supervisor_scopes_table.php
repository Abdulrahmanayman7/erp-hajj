<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * User + season supervisor grant. No facility_id. users.id only.
 *
 * @see docs/09-modules/18-accommodation-and-camps/DATA_MODEL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accommodation_supervisor_scopes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants', indexName: 'acc_ssc_tenant_fk')->restrictOnDelete();
            $table->unsignedBigInteger('season_id');
            $table->foreignId('user_id')->constrained('users', indexName: 'acc_ssc_user_fk')->restrictOnDelete();
            $table->foreignId('granted_by')->nullable()->constrained('users', indexName: 'acc_ssc_granted_by_fk')->restrictOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->unsignedTinyInteger('active_grant')
                ->nullable()
                ->storedAs('case when revoked_at is null then 1 else null end');
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'season_id', 'user_id', 'active_grant'],
                'acc_ssc_live_grant_uq',
            );
            $table->foreign(['tenant_id', 'season_id'], 'acc_ssc_season_fk')
                ->references(['tenant_id', 'id'])
                ->on('seasons')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accommodation_supervisor_scopes');
    }
};
