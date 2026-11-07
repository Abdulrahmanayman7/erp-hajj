<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive nullable S06 columns on tasks (D01 / E-Q01 / E-Q05).
 * Existing MVP rows remain valid with NULL project/season.
 *
 * @see docs/09-modules/20-season-projects/DATA_MODEL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->unsignedBigInteger('season_id')->nullable()->after('decision_id');
            $table->unsignedBigInteger('project_id')->nullable()->after('season_id');
            $table->unsignedBigInteger('phase_id')->nullable()->after('project_id');
            $table->string('delay_state', 16)->default('none')->after('phase_id');
            $table->timestamp('delay_calculated_at')->nullable()->after('delay_state');

            $table->foreign(['tenant_id', 'season_id'], 'tasks_season_fk')
                ->references(['tenant_id', 'id'])
                ->on('seasons')
                ->restrictOnDelete();
            $table->foreign('project_id', 'tasks_project_fk')
                ->references('id')
                ->on('season_projects')
                ->restrictOnDelete();
            $table->foreign('phase_id', 'tasks_phase_fk')
                ->references('id')
                ->on('season_project_phases')
                ->restrictOnDelete();

            $table->index(['tenant_id', 'project_id', 'status'], 'tasks_tenant_project_status_idx');
            $table->index(['tenant_id', 'delay_state'], 'tasks_tenant_delay_state_idx');
        });
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        Schema::table('tasks', function (Blueprint $table) use ($driver): void {
            if ($driver !== 'sqlite') {
                $table->dropIndex('tasks_tenant_project_status_idx');
                $table->dropIndex('tasks_tenant_delay_state_idx');
                $table->dropForeign('tasks_season_fk');
                $table->dropForeign('tasks_project_fk');
                $table->dropForeign('tasks_phase_fk');
            }

            $table->dropColumn([
                'season_id',
                'project_id',
                'phase_id',
                'delay_state',
                'delay_calculated_at',
            ]);
        });
    }
};
