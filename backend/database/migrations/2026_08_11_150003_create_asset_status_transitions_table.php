<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only asset status transitions.
 *
 * @see docs/09-modules/11-assets-and-custodies/DATA_MODEL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_status_transitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('asset_id')->constrained('assets')->restrictOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->string('reason', 1000)->nullable();
            $table->foreignId('performed_by')->constrained('users')->restrictOnDelete();
            $table->string('correlation_id', 64)->nullable();
            $table->unsignedBigInteger('custody_id')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'asset_id', 'created_at'], 'asset_status_transitions_tenant_asset_created_idx');
            $table->index(['tenant_id', 'to_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_status_transitions');
    }
};
