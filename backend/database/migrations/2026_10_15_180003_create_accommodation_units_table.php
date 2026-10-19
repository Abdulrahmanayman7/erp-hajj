<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stable room or tent. structure_id is optional and must belong to the same facility.
 *
 * @see docs/09-modules/18-accommodation-and-camps/DATA_MODEL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accommodation_units', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants', indexName: 'acc_unit_tenant_fk')->restrictOnDelete();
            $table->unsignedBigInteger('facility_id');
            $table->unsignedBigInteger('structure_id')->nullable();
            $table->string('unit_kind', 32);
            $table->string('code', 40);
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'acc_unit_created_by_fk')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'id'], 'acc_unit_tenant_id_uq');
            $table->unique(['tenant_id', 'facility_id', 'id'], 'acc_unit_tenant_fac_id_uq');
            $table->unique(['tenant_id', 'facility_id', 'code'], 'acc_unit_tenant_fac_code_uq');

            $table->foreign(['tenant_id', 'facility_id'], 'acc_unit_facility_fk')
                ->references(['tenant_id', 'id'])
                ->on('accommodation_facilities')
                ->restrictOnDelete();
            $table->foreign(['tenant_id', 'facility_id', 'structure_id'], 'acc_unit_structure_fk')
                ->references(['tenant_id', 'facility_id', 'id'])
                ->on('accommodation_structures')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accommodation_units');
    }
};
