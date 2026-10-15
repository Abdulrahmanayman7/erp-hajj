<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Run Transport Supervisor history (ADR-0018 T4/T7). The supervisor is an
 * Employee; overlap across Runs is allowed. At most one current per Run.
 *
 * @see docs/09-modules/17-transportation-and-fleet/DATA_MODEL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transport_run_supervisor_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants', indexName: 'tr_run_supervisors_tenant_fk')->restrictOnDelete();
            $table->unsignedBigInteger('season_id');
            $table->unsignedBigInteger('vehicle_run_id');
            $table->foreignId('supervisor_employee_id')->constrained('employees', indexName: 'tr_run_supervisors_employee_fk')->restrictOnDelete();
            $table->dateTime('assigned_at');
            $table->foreignId('assigned_by')->nullable()->constrained('users', indexName: 'tr_run_supervisors_assigned_by_fk')->restrictOnDelete();
            $table->dateTime('ended_at')->nullable();
            $table->foreignId('ended_by')->nullable()->constrained('users', indexName: 'tr_run_supervisors_ended_by_fk')->restrictOnDelete();
            $table->string('reason', 1000)->nullable();
            $table->unsignedTinyInteger('current_flag')
                ->nullable()
                ->storedAs('case when ended_at is null then 1 else null end');
            $table->timestamps();

            $table->foreign(['tenant_id', 'season_id'], 'tr_run_supervisors_season_fk')
                ->references(['tenant_id', 'id'])
                ->on('seasons')
                ->restrictOnDelete();
            $table->foreign(['season_id', 'vehicle_run_id'], 'tr_run_supervisors_run_fk')
                ->references(['season_id', 'id'])
                ->on('transport_vehicle_runs')
                ->restrictOnDelete();

            $table->unique(['vehicle_run_id', 'current_flag'], 'tr_run_supervisors_current_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transport_run_supervisor_assignments');
    }
};
