<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant-level structured origins/destinations (ADR-0018 T2). No link to
 * any other domain yet; future links are additive typed columns/tables.
 *
 * @see docs/09-modules/17-transportation-and-fleet/DATA_MODEL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transport_points', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants', indexName: 'tr_points_tenant_fk')->restrictOnDelete();
            $table->string('code', 40);
            $table->string('name', 255);
            $table->string('point_type', 32);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'tr_points_created_by_fk')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'id'], 'tr_points_tenant_id_unique');
            $table->unique(['tenant_id', 'code'], 'tr_points_tenant_code_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transport_points');
    }
};
