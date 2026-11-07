<?php

namespace App\Modules\Tasks\Console;

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantContext;
use App\Modules\Tasks\Enums\TaskStatus;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Support\TaskDelaySynchronizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Idempotent delay projection recompute (E-Q05).
 */
final class RecomputeTaskDelayCommand extends Command
{
    protected $signature = 'tasks:recompute-delay {--tenant= : Optional tenant id} {--chunk=200}';

    protected $description = 'Recompute persisted task delay_state projection from calculated delay';

    public function handle(TenantContext $tenantContext, TaskDelaySynchronizer $delay): int
    {
        $chunk = max(1, (int) $this->option('chunk'));
        $tenantFilter = $this->option('tenant');

        $tenants = Tenant::query()
            ->when($tenantFilter !== null && $tenantFilter !== '', fn ($q) => $q->whereKey((int) $tenantFilter))
            ->orderBy('id')
            ->get();

        $changed = 0;
        $scanned = 0;

        foreach ($tenants as $tenant) {
            $tenantContext->runAsTenant($tenant, function () use ($delay, $chunk, &$changed, &$scanned): void {
                $open = array_map(
                    static fn (TaskStatus $s): string => $s->value,
                    TaskStatus::openStatuses(),
                );

                Task::query()
                    ->where(function ($query) use ($open): void {
                        $query->whereIn('status', $open)
                            ->orWhere('delay_state', 'delayed');
                    })
                    ->orderBy('id')
                    ->chunkById($chunk, function ($tasks) use ($delay, &$changed, &$scanned): void {
                        foreach ($tasks as $task) {
                            DB::transaction(function () use ($task, $delay, &$changed, &$scanned): void {
                                /** @var Task $locked */
                                $locked = Task::query()->whereKey($task->id)->lockForUpdate()->first();
                                if ($locked === null) {
                                    return;
                                }
                                $scanned++;
                                if ($delay->sync($locked)) {
                                    $changed++;
                                }
                            });
                        }
                    });
            });
        }

        $this->info("Scanned {$scanned} tasks; updated {$changed} delay projections.");

        return self::SUCCESS;
    }
}
