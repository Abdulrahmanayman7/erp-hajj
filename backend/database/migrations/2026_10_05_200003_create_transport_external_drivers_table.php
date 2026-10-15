<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Minimal hired-driver identity (ADR-0018 T4). Not an Employee, User, or
 * HR record.
 *
 * @see docs/09-modules/17-transportation-and-fleet/DATA_MODEL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transport_external_drivers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants', indexName: 'tr_ext_drivers_tenant_fk')->restrictOnDelete();
            $table->string('full_name', 150);
            $table->string('phone', 30)->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'tr_ext_drivers_created_by_fk')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'id'], 'tr_ext_drivers_tenant_id_unique');
            $table->index(['tenant_id', 'full_name'], 'tr_ext_drivers_tenant_name_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transport_external_drivers');
    }
};
