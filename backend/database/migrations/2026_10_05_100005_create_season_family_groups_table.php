<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Season-specific family groups (F8). Membership and the primary member
 * are stored on season_pilgrims.
 *
 * @see docs/09-modules/16-season-and-pilgrims/DATA_MODEL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('season_family_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->unsignedBigInteger('season_id');
            $table->string('name', 255);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->foreign(['tenant_id', 'season_id'], 'season_family_groups_season_fk')
                ->references(['tenant_id', 'id'])
                ->on('seasons')
                ->restrictOnDelete();

            $table->unique(['season_id', 'id'], 'season_family_groups_season_id_id_unique');
            $table->index(['tenant_id', 'season_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('season_family_groups');
    }
};
