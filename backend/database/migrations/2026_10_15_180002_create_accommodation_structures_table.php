<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional building or floor under a facility. Parent must be the same facility.
 *
 * @see docs/09-modules/18-accommodation-and-camps/DATA_MODEL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accommodation_structures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants', indexName: 'acc_str_tenant_fk')->restrictOnDelete();
            $table->unsignedBigInteger('facility_id');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('kind', 32);
            $table->string('name', 255);
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'acc_str_created_by_fk')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'id'], 'acc_str_tenant_id_uq');
            $table->unique(['tenant_id', 'facility_id', 'id'], 'acc_str_tenant_fac_id_uq');

            $table->foreign(['tenant_id', 'facility_id'], 'acc_str_facility_fk')
                ->references(['tenant_id', 'id'])
                ->on('accommodation_facilities')
                ->restrictOnDelete();
            $table->foreign(['tenant_id', 'facility_id', 'parent_id'], 'acc_str_parent_fk')
                ->references(['tenant_id', 'facility_id', 'id'])
                ->on('accommodation_structures')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accommodation_structures');
    }
};
