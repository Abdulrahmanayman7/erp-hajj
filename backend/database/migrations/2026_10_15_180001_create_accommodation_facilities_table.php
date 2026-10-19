<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stable hotel or camp identity. No season_id. Sprint 1 only.
 *
 * @see docs/09-modules/18-accommodation-and-camps/DATA_MODEL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accommodation_facilities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants', indexName: 'acc_fac_tenant_fk')->restrictOnDelete();
            $table->string('kind', 32);
            $table->string('code', 40);
            $table->string('name', 255);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'acc_fac_created_by_fk')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'id'], 'acc_fac_tenant_id_uq');
            $table->unique(['tenant_id', 'code'], 'acc_fac_tenant_code_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accommodation_facilities');
    }
};
