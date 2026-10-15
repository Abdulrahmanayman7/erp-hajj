<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Run driver assignment history (ADR-0018 T4/T7). The driver is either an
 * internal Employee (`driver_employee_id`) or an ExternalDriver
 * (`external_driver_id`), each with a constrained FK — no morph columns.
 * Exactly one is set (action-enforced; the project uses no CHECK
 * constraints). At most one current driver per Run via `current_flag`.
 *
 * @see docs/09-modules/17-transportation-and-fleet/DATA_MODEL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transport_run_driver_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants', indexName: 'tr_run_drivers_tenant_fk')->restrictOnDelete();
            $table->unsignedBigInteger('season_id');
            $table->unsignedBigInteger('vehicle_run_id');
            $table->foreignId('driver_employee_id')->nullable()->constrained('employees', indexName: 'tr_run_drivers_employee_fk')->restrictOnDelete();
            $table->unsignedBigInteger('external_driver_id')->nullable();
            $table->dateTime('assigned_at');
            $table->foreignId('assigned_by')->nullable()->constrained('users', indexName: 'tr_run_drivers_assigned_by_fk')->restrictOnDelete();
            $table->dateTime('ended_at')->nullable();
            $table->foreignId('ended_by')->nullable()->constrained('users', indexName: 'tr_run_drivers_ended_by_fk')->restrictOnDelete();
            $table->string('reason', 1000)->nullable();
            $table->unsignedTinyInteger('current_flag')
                ->nullable()
                ->storedAs('case when ended_at is null then 1 else null end');
            $table->timestamps();

            $table->foreign(['tenant_id', 'season_id'], 'tr_run_drivers_season_fk')
                ->references(['tenant_id', 'id'])
                ->on('seasons')
                ->restrictOnDelete();
            $table->foreign(['season_id', 'vehicle_run_id'], 'tr_run_drivers_run_fk')
                ->references(['season_id', 'id'])
                ->on('transport_vehicle_runs')
                ->restrictOnDelete();
            $table->foreign(['tenant_id', 'external_driver_id'], 'tr_run_drivers_external_fk')
                ->references(['tenant_id', 'id'])
                ->on('transport_external_drivers')
                ->restrictOnDelete();

            $table->unique(['vehicle_run_id', 'current_flag'], 'tr_run_drivers_current_unique');
            $table->index(['tenant_id', 'external_driver_id'], 'tr_run_drivers_tenant_external_index');
            $table->index(['driver_employee_id', 'ended_at'], 'tr_run_drivers_employee_current_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transport_run_driver_assignments');
    }
};
