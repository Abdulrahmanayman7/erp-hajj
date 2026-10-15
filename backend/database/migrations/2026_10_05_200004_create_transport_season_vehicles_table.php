<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A Vehicle's participation in one Season (ADR-0018 T3/T4/T5): operational
 * capacity, service level, and planning default crew.
 *
 * - UNIQUE (season_id, transport_vehicle_id) → no duplicate per season.
 * - (tenant_id, transport_vehicle_id) → transport_vehicles (tenant_id, id).
 * - (tenant_id, default_external_driver_id) → transport_external_drivers.
 * Employee defaults keep single-column FKs (`employees` has no
 * (tenant_id, id) key); the action resolves them tenant-scoped. At most one
 * default driver kind is set (action-enforced).
 *
 * @see docs/09-modules/17-transportation-and-fleet/DATA_MODEL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transport_season_vehicles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants', indexName: 'tr_season_vehicles_tenant_fk')->restrictOnDelete();
            $table->unsignedBigInteger('season_id');
            $table->unsignedBigInteger('transport_vehicle_id');
            $table->unsignedSmallInteger('operational_capacity');
            $table->string('service_level', 32);
            $table->foreignId('default_driver_employee_id')->nullable()->constrained('employees', indexName: 'tr_season_vehicles_def_driver_fk')->restrictOnDelete();
            $table->unsignedBigInteger('default_external_driver_id')->nullable();
            $table->foreignId('default_supervisor_employee_id')->nullable()->constrained('employees', indexName: 'tr_season_vehicles_def_supervisor_fk')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'tr_season_vehicles_created_by_fk')->restrictOnDelete();
            $table->timestamps();

            $table->foreign(['tenant_id', 'season_id'], 'tr_season_vehicles_season_fk')
                ->references(['tenant_id', 'id'])
                ->on('seasons')
                ->restrictOnDelete();
            $table->foreign(['tenant_id', 'transport_vehicle_id'], 'tr_season_vehicles_vehicle_fk')
                ->references(['tenant_id', 'id'])
                ->on('transport_vehicles')
                ->restrictOnDelete();
            $table->foreign(['tenant_id', 'default_external_driver_id'], 'tr_season_vehicles_def_ext_driver_fk')
                ->references(['tenant_id', 'id'])
                ->on('transport_external_drivers')
                ->restrictOnDelete();

            $table->unique(['season_id', 'id'], 'tr_season_vehicles_season_id_unique');
            $table->unique(['season_id', 'transport_vehicle_id'], 'tr_season_vehicles_season_vehicle_unique');
            $table->index(['tenant_id', 'transport_vehicle_id'], 'tr_season_vehicles_tenant_vehicle_index');
            $table->index(['tenant_id', 'default_external_driver_id'], 'tr_season_vehicles_tenant_ext_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transport_season_vehicles');
    }
};
