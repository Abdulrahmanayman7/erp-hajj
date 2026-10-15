<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pilgrim group supervisor team with history (F27). Rows are never deleted;
 * an assignment ends by setting ended_at.
 *
 * Generated flags (1 while the row is current, NULL once ended) give:
 * - UNIQUE (group, employee, active_flag)          → an employee holds at most one current role per group;
 * - UNIQUE (group, primary_active_flag)            → at most one current primary supervisor per group.
 *
 * (tenant_id, season_pilgrim_group_id) → season_pilgrim_groups (tenant_id, id)
 * forbids a supervisor row whose tenant differs from its group. employee_id
 * keeps a single-column FK: `employees` (Employees module) has no
 * (tenant_id, id) key; the assigning action resolves the Employee through
 * the tenant-scoped query.
 *
 * @see docs/09-modules/16-season-and-pilgrims/DATA_MODEL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('season_pilgrim_group_supervisors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants', indexName: 'sp_group_supervisors_tenant_fk')->restrictOnDelete();
            $table->unsignedBigInteger('season_pilgrim_group_id');
            $table->foreignId('employee_id')->constrained('employees', indexName: 'sp_group_supervisors_employee_fk')->restrictOnDelete();
            $table->string('role', 16);
            $table->timestamp('assigned_at');
            $table->foreignId('assigned_by')->nullable()->constrained('users', indexName: 'sp_group_supervisors_assigned_by_fk')->restrictOnDelete();
            $table->timestamp('ended_at')->nullable();
            $table->foreignId('ended_by')->nullable()->constrained('users', indexName: 'sp_group_supervisors_ended_by_fk')->restrictOnDelete();
            $table->unsignedTinyInteger('active_flag')
                ->nullable()
                ->storedAs('case when ended_at is null then 1 else null end');
            $table->unsignedTinyInteger('primary_active_flag')
                ->nullable()
                ->storedAs("case when ended_at is null and role = 'primary' then 1 else null end");
            $table->timestamps();

            $table->foreign(['tenant_id', 'season_pilgrim_group_id'], 'sp_group_supervisors_group_fk')
                ->references(['tenant_id', 'id'])
                ->on('season_pilgrim_groups')
                ->restrictOnDelete();

            $table->unique(
                ['season_pilgrim_group_id', 'employee_id', 'active_flag'],
                'sp_group_supervisors_active_employee_unique',
            );
            $table->unique(
                ['season_pilgrim_group_id', 'primary_active_flag'],
                'sp_group_supervisors_active_primary_unique',
            );
            $table->index(['tenant_id', 'employee_id'], 'sp_group_supervisors_tenant_employee_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('season_pilgrim_group_supervisors');
    }
};
