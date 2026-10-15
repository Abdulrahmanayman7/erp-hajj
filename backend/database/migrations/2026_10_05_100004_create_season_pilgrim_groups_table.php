<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Season-specific operational pilgrim groups (not OrganizationUnit, F26).
 *
 * @see docs/09-modules/16-season-and-pilgrims/DATA_MODEL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('season_pilgrim_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->unsignedBigInteger('season_id');
            $table->string('code', 40);
            $table->string('name', 255);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->foreign(['tenant_id', 'season_id'], 'season_pilgrim_groups_season_fk')
                ->references(['tenant_id', 'id'])
                ->on('seasons')
                ->restrictOnDelete();

            $table->unique(['season_id', 'id'], 'season_pilgrim_groups_season_id_id_unique');
            $table->unique(['tenant_id', 'id'], 'season_pilgrim_groups_tenant_id_id_unique');
            $table->unique(['tenant_id', 'season_id', 'code'], 'season_pilgrim_groups_season_code_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('season_pilgrim_groups');
    }
};
