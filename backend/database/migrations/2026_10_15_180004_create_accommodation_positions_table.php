<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stable bed or tent position under one unit.
 *
 * @see docs/09-modules/18-accommodation-and-camps/DATA_MODEL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accommodation_positions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants', indexName: 'acc_pos_tenant_fk')->restrictOnDelete();
            $table->unsignedBigInteger('unit_id');
            $table->string('code', 40);
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'acc_pos_created_by_fk')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'id'], 'acc_pos_tenant_id_uq');
            $table->unique(['tenant_id', 'unit_id', 'id'], 'acc_pos_tenant_unit_id_uq');
            $table->unique(['tenant_id', 'unit_id', 'code'], 'acc_pos_tenant_unit_code_uq');

            $table->foreign(['tenant_id', 'unit_id'], 'acc_pos_unit_fk')
                ->references(['tenant_id', 'id'])
                ->on('accommodation_units')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accommodation_positions');
    }
};
