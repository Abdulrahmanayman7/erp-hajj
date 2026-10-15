<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-tenant, per-season movement number sequence (MOV-######).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transport_movement_sequences', function (Blueprint $table): void {
            $table->foreignId('tenant_id')->constrained('tenants', indexName: 'tr_movement_seq_tenant_fk')->restrictOnDelete();
            $table->unsignedBigInteger('season_id');
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();

            $table->primary(['tenant_id', 'season_id']);
            $table->foreign(['tenant_id', 'season_id'], 'tr_movement_seq_season_fk')
                ->references(['tenant_id', 'id'])
                ->on('seasons')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transport_movement_sequences');
    }
};
