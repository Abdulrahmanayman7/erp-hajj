<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Typed Transportation entitlement per SeasonProgram (ADR-0018 T9). A null
 * field means "any"; at least one field is set (action-enforced).
 *
 * @see docs/09-modules/17-transportation-and-fleet/DATA_MODEL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transport_entitlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants', indexName: 'tr_entitlements_tenant_fk')->restrictOnDelete();
            $table->unsignedBigInteger('season_id');
            $table->unsignedBigInteger('season_program_id');
            $table->string('vehicle_class', 32)->nullable();
            $table->string('service_level', 32)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'tr_entitlements_created_by_fk')->restrictOnDelete();
            $table->timestamps();

            $table->foreign(['tenant_id', 'season_id'], 'tr_entitlements_season_fk')
                ->references(['tenant_id', 'id'])
                ->on('seasons')
                ->restrictOnDelete();
            $table->foreign(['season_id', 'season_program_id'], 'tr_entitlements_program_fk')
                ->references(['season_id', 'id'])
                ->on('season_programs')
                ->restrictOnDelete();

            $table->unique(['season_id', 'season_program_id'], 'tr_entitlements_season_program_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transport_entitlements');
    }
};
