<?php

namespace App\Core\Demo\Console;

use App\Core\Authorization\Actions\BootstrapTenantOwner;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Creates an isolated, disposable dataset for local performance validation.
 *
 * Fixture rows deliberately bypass domain actions to keep seeding practical;
 * benchmarked mutations still use the real Actions.
 */
final class SeedPerformanceDatasetCommand extends Command
{
    private const TENANT_CODE = 'perf_large';

    private const OWNER_EMAIL = 'owner@perf-large.invalid';

    /** @var array<string, int> */
    private const TARGETS = [
        'organization_units' => 250,
        'users' => 1000,
        'employees' => 5000,
        'contracts' => 10000,
        'meetings' => 10000,
        'decisions' => 15000,
        'tasks' => 75000,
        'warehouses' => 50,
        'inventory_items' => 15000,
        'inventory_balances' => 30000,
        'inventory_movements' => 150000,
        'assets' => 20000,
        'asset_custodies' => 40000,
        'notifications' => 200000,
        'audit_logs' => 500000,
    ];

    protected $signature = 'performance:seed
                            {--confirm-perf : Explicit confirmation that this is a disposable local performance fixture}
                            {--database-name=erp_hajj_perf : Isolated database name; must contain "perf"}
                            {--rebuild : Drop and recreate the isolated performance database}
                            {--scale=medium : Dataset profile; medium is the documented target}';

    protected $description = 'Create an isolated perf_large tenant with bulk-inserted local performance fixtures';

    public function handle(BootstrapTenantOwner $bootstrapOwner): int
    {
        if (app()->environment('production')) {
            $this->error('Refusing to seed performance fixtures in production.');

            return self::FAILURE;
        }

        if (! $this->option('confirm-perf')) {
            $this->error('Refusing to run without --confirm-perf.');

            return self::FAILURE;
        }

        $database = (string) $this->option('database-name');
        if (! preg_match('/^[A-Za-z0-9_]+$/', $database) || ! str_contains(mb_strtolower($database), 'perf')) {
            $this->error('Database name must be alphanumeric/underscore and contain "perf".');

            return self::FAILURE;
        }

        if ($this->option('scale') !== 'medium') {
            $this->error('Only the documented medium profile is supported.');

            return self::FAILURE;
        }

        $originalDatabase = (string) config('database.connections.mysql.database');

        try {
            if ($this->option('rebuild')) {
                $this->dropDatabase($database);
            }

            $this->ensureDatabase($database);
            $this->switchDatabase($database);

            $this->info("Migrating isolated performance database [{$database}]...");
            if (Artisan::call('migrate', ['--force' => true]) !== self::SUCCESS) {
                $this->output->write(Artisan::output());
                throw new RuntimeException('Migration failed.');
            }

            if (Tenant::query()->where('tenant_code', self::TENANT_CODE)->exists()) {
                throw new RuntimeException('perf_large already exists. Re-run with --rebuild to replace the isolated database.');
            }

            $tenant = Tenant::query()->create([
                'tenant_code' => self::TENANT_CODE,
                'name' => 'Performance Large Fixture',
                'status' => TenantStatus::Active,
                'locale' => 'ar',
                'timezone' => 'Asia/Riyadh',
                'notes' => 'DISPOSABLE LOCAL PERFORMANCE FIXTURE — never production data',
            ]);

            $owner = $bootstrapOwner->execute(
                self::TENANT_CODE,
                'Performance Owner',
                self::OWNER_EMAIL,
                'PerfFixture@123',
                false,
                false,
            );

            DB::disableQueryLog();
            $counts = $this->seed($tenant->id, (int) $owner['user_id']);
            $this->table(['Table', 'Rows'], collect($counts)->map(fn (int $count, string $table): array => [$table, $count])->all());
            $this->info("Performance fixture ready in [{$database}] for tenant [".self::TENANT_CODE.'].');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } finally {
            $this->switchDatabase($originalDatabase);
        }
    }

    /**
     * @return array<string, int>
     */
    private function seed(int $tenantId, int $ownerId): array
    {
        $now = now()->utc()->format('Y-m-d H:i:s');
        $today = Carbon::now('Asia/Riyadh')->startOfDay();

        $this->info('Bulk seeding catalogs, identities, and organization...');
        $this->insertChunked('users', $this->users($tenantId, $now), 500);
        $userIds = DB::table('users')->where('tenant_id', $tenantId)->orderBy('id')->pluck('id')->map(fn ($id): int => (int) $id)->all();
        array_unshift($userIds, $ownerId);
        $userIds = array_values(array_unique($userIds));

        $this->insertChunked('organization_units', $this->organizationUnits($tenantId, $userIds, $now), 250);
        $unitIds = $this->ids('organization_units', $tenantId);
        $this->insertChunked('positions', $this->positions($tenantId, $now), 50);
        $positionIds = $this->ids('positions', $tenantId);
        $this->insertChunked('employees', $this->employees($tenantId, $userIds, $unitIds, $positionIds, $now), 500);
        $employeeIds = $this->ids('employees', $tenantId);

        $this->insertChunked('contract_categories', $this->simpleCategories('contract', $tenantId, $now), 20);
        $this->insertChunked('inventory_categories', $this->simpleCategories('inventory', $tenantId, $now), 20);
        $this->insertChunked('asset_categories', $this->simpleCategories('asset', $tenantId, $now), 20);
        $contractCategoryIds = $this->ids('contract_categories', $tenantId);
        $inventoryCategoryIds = $this->ids('inventory_categories', $tenantId);
        $assetCategoryIds = $this->ids('asset_categories', $tenantId);

        $this->info('Bulk seeding governance and execution data...');
        $this->insertGenerated('contracts', self::TARGETS['contracts'], 500, function (int $i) use ($tenantId, $ownerId, $unitIds, $employeeIds, $contractCategoryIds, $today, $now): array {
            $status = ['draft', 'in_review', 'approved', 'signed', 'executing', 'expired'][$i % 6];
            $endDate = $today->copy()->addDays(($i % 730) - 365)->toDateString();

            return [
                'tenant_id' => $tenantId,
                'contract_number' => sprintf('CTR-%06d', $i + 1),
                'title' => 'عقد أداء موسمي '.$i,
                'contract_category_id' => $contractCategoryIds[$i % count($contractCategoryIds)],
                'status' => $status,
                'counterparty_name' => 'جهة تعاقدية '.$i,
                'counterparty_kind' => 'organization',
                'employee_id' => $employeeIds[$i % count($employeeIds)],
                'organization_unit_id' => $unitIds[$i % count($unitIds)],
                'start_date' => $today->copy()->subDays(365 - ($i % 365))->toDateString(),
                'end_date' => $endDate,
                'value' => 10000 + ($i % 100000),
                'currency' => 'SAR',
                'notes' => null,
                'created_by' => $ownerId,
                'renewed_from_contract_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        });

        $this->insertGenerated('meetings', self::TARGETS['meetings'], 500, function (int $i) use ($tenantId, $ownerId, $unitIds, $employeeIds, $today, $now): array {
            $scheduledAt = $today->copy()->addMinutes(($i % 2880) - 1440)->utc()->format('Y-m-d H:i:s');
            $status = ['scheduled', 'in_progress', 'completed', 'cancelled'][$i % 4];

            return [
                'tenant_id' => $tenantId,
                'meeting_number' => sprintf('MTG-%06d', $i + 1),
                'title' => 'اجتماع تشغيلي '.$i,
                'description' => null,
                'status' => $status,
                'scheduled_at' => $scheduledAt,
                'started_at' => $status === 'in_progress' ? $scheduledAt : null,
                'ended_at' => $status === 'completed' ? $scheduledAt : null,
                'location_type' => 'physical',
                'location_text' => 'قاعة الأداء',
                'meeting_link' => null,
                'organization_unit_id' => $unitIds[$i % count($unitIds)],
                'chairperson_employee_id' => $employeeIds[$i % count($employeeIds)],
                'secretary_employee_id' => $employeeIds[($i + 1) % count($employeeIds)],
                'minutes_body' => null,
                'notes' => null,
                'created_by' => $ownerId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        });

        $this->insertGenerated('decisions', self::TARGETS['decisions'], 500, function (int $i) use ($tenantId, $ownerId, $unitIds, $employeeIds, $today, $now): array {
            return [
                'tenant_id' => $tenantId,
                'decision_number' => sprintf('DEC-%06d', $i + 1),
                'title' => 'قرار تشغيلي '.$i,
                'body' => 'نص قرار تجريبي لأغراض قياس الأداء فقط.',
                'notes' => null,
                'status' => ['draft', 'pending_approval', 'approved', 'closed'][$i % 4],
                'source_recommendation_id' => null,
                'organization_unit_id' => $unitIds[$i % count($unitIds)],
                'issued_by_employee_id' => $employeeIds[$i % count($employeeIds)],
                'responsible_employee_id' => $employeeIds[($i + 1) % count($employeeIds)],
                'effective_date' => $today->copy()->subDays($i % 365)->toDateString(),
                'due_date' => $today->copy()->addDays(($i % 365) - 180)->toDateString(),
                'created_by' => $ownerId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        });
        $decisionIds = $this->ids('decisions', $tenantId);

        $this->insertGenerated('tasks', self::TARGETS['tasks'], 500, function (int $i) use ($tenantId, $ownerId, $unitIds, $employeeIds, $decisionIds, $today, $now): array {
            $bucket = $i % 10;
            $dueDate = match (true) {
                $bucket < 2 => $today->copy()->subDays(($i % 60) + 1),
                $bucket < 4 => $today->copy(),
                $bucket < 6 => $today->copy()->addDays(($i % 3) + 1),
                default => $today->copy()->addDays(($i % 360) + 4),
            };
            $status = $bucket < 6 ? ['assigned', 'in_progress'][$i % 2] : ['draft', 'completed', 'cancelled'][$i % 3];

            return [
                'tenant_id' => $tenantId,
                'task_number' => sprintf('TSK-%06d', $i + 1),
                'title' => ($i % 100 === 0 ? 'مهمة بحث الحج ' : 'مهمة تشغيلية ').$i,
                'description' => null,
                'notes' => null,
                'status' => $status,
                'priority' => ['low', 'medium', 'high'][$i % 3],
                'decision_id' => $decisionIds[$i % count($decisionIds)],
                'organization_unit_id' => $unitIds[$i % count($unitIds)],
                'assigned_to_employee_id' => $employeeIds[$i % 1000],
                'progress_percent' => $status === 'completed' ? 100 : ($i % 90),
                'start_date' => $today->copy()->subDays($i % 30)->toDateString(),
                'due_date' => $dueDate->toDateString(),
                'completed_at' => $status === 'completed' ? $now : null,
                'completion_notes' => $status === 'completed' ? 'مكتملة' : null,
                'created_by' => $ownerId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        });

        $this->info('Bulk seeding inventory and assets...');
        $this->insertGenerated('warehouses', self::TARGETS['warehouses'], 50, function (int $i) use ($tenantId, $ownerId, $unitIds, $employeeIds, $now): array {
            return [
                'tenant_id' => $tenantId,
                'warehouse_number' => sprintf('WH-%06d', $i + 1),
                'name' => 'مستودع الأداء '.$i,
                'description' => null,
                'location' => 'موقع '.$i,
                'organization_unit_id' => $unitIds[$i % count($unitIds)],
                'responsible_employee_id' => $employeeIds[$i % 1000],
                'is_active' => true,
                'notes' => null,
                'created_by' => $ownerId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        });
        $warehouseIds = $this->ids('warehouses', $tenantId);

        $this->insertGenerated('inventory_items', self::TARGETS['inventory_items'], 500, function (int $i) use ($tenantId, $ownerId, $inventoryCategoryIds, $now): array {
            return [
                'tenant_id' => $tenantId,
                'item_number' => sprintf('ITM-%06d', $i + 1),
                'name' => ($i % 100 === 0 ? 'صنف بحث الحج ' : 'صنف مخزون ').$i,
                'description' => null,
                'category_id' => $inventoryCategoryIds[$i % count($inventoryCategoryIds)],
                'unit' => 'piece',
                'barcode' => sprintf('PERF-%08d', $i + 1),
                'minimum_stock' => 10 + ($i % 90),
                'is_active' => true,
                'notes' => null,
                'created_by' => $ownerId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        });
        $itemIds = $this->ids('inventory_items', $tenantId);

        $this->insertGenerated('inventory_balances', self::TARGETS['inventory_balances'], 500, function (int $i) use ($tenantId, $warehouseIds, $itemIds, $now): array {
            return [
                'tenant_id' => $tenantId,
                'warehouse_id' => $warehouseIds[$i % count($warehouseIds)],
                'inventory_item_id' => $itemIds[intdiv($i, count($warehouseIds))],
                'on_hand' => $i % 10 === 0 ? 0 : 20 + ($i % 300),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        });

        $this->insertGenerated('inventory_movements', self::TARGETS['inventory_movements'], 500, function (int $i) use ($tenantId, $ownerId, $warehouseIds, $itemIds, $today, $now): array {
            $movement = [
                ['opening', 'in'],
                ['receipt', 'in'],
                ['issue', 'out'],
                ['return', 'in'],
                ['adjustment', 'out'],
            ][$i % 5];

            return [
                'tenant_id' => $tenantId,
                'movement_number' => sprintf('MOV-%06d', $i + 1),
                'type' => $movement[0],
                'warehouse_id' => $warehouseIds[$i % count($warehouseIds)],
                'inventory_item_id' => $itemIds[$i % count($itemIds)],
                'quantity' => 1 + ($i % 25),
                'direction' => $movement[1],
                'balance_before' => 100,
                'balance_after' => 100 + ($i % 25),
                'transfer_group_id' => null,
                'reason' => 'حركة أداء '.$i,
                'reference' => $i % 50 === 0 ? 'REF-PERF-'.$i : null,
                'performed_by' => $ownerId,
                'occurred_at' => $today->copy()->subDays($i % 90)->addMinutes($i % 1440)->format('Y-m-d H:i:s'),
                'correlation_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        });

        $this->insertGenerated('assets', self::TARGETS['assets'], 500, function (int $i) use ($tenantId, $ownerId, $warehouseIds, $unitIds, $assetCategoryIds, $today, $now): array {
            return [
                'tenant_id' => $tenantId,
                'asset_number' => sprintf('AST-%06d', $i + 1),
                'name' => ($i % 100 === 0 ? 'أصل بحث الحج ' : 'أصل تشغيلي ').$i,
                'description' => null,
                'category_id' => $assetCategoryIds[$i % count($assetCategoryIds)],
                'serial_number' => sprintf('SER-%08d', $i + 1),
                'barcode' => sprintf('ASTBC-%08d', $i + 1),
                'status' => 'available',
                'condition' => 'good',
                'warehouse_id' => $warehouseIds[$i % count($warehouseIds)],
                'organization_unit_id' => $unitIds[$i % count($unitIds)],
                'purchase_value' => 1000 + ($i % 5000),
                'acquisition_date' => $today->copy()->subDays($i % 1000)->toDateString(),
                'current_custody_id' => null,
                'notes' => null,
                'created_by' => $ownerId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        });
        $assetIds = $this->ids('assets', $tenantId);

        $this->insertGenerated('asset_custodies', self::TARGETS['asset_custodies'], 500, function (int $i) use ($tenantId, $ownerId, $assetIds, $employeeIds, $today, $now): array {
            $active = $i < 10000;
            $assignedAt = $today->copy()->subDays($i % 90)->format('Y-m-d H:i:s');

            return [
                'tenant_id' => $tenantId,
                'custody_number' => sprintf('CUS-%06d', $i + 1),
                'asset_id' => $assetIds[$i % count($assetIds)],
                'employee_id' => $employeeIds[$i % 1000],
                'status' => $active ? 'active' : 'returned',
                'assigned_at' => $assignedAt,
                'expected_return_at' => $active ? $today->copy()->addDays(($i % 6) - 3)->format('Y-m-d H:i:s') : null,
                'returned_at' => $active ? null : $today->copy()->subDays($i % 90)->format('Y-m-d H:i:s'),
                'assigned_by' => $ownerId,
                'returned_by' => $active ? null : $ownerId,
                'condition_at_assignment' => 'good',
                'condition_at_return' => $active ? null : 'good',
                'assignment_notes' => null,
                'return_notes' => null,
                'correlation_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        });
        DB::statement(
            'UPDATE assets INNER JOIN asset_custodies ON asset_custodies.asset_id = assets.id
             SET assets.current_custody_id = asset_custodies.id, assets.status = ?
             WHERE assets.tenant_id = ? AND asset_custodies.tenant_id = ? AND asset_custodies.status = ?',
            ['in_use', $tenantId, $tenantId, 'active'],
        );

        $this->info('Bulk seeding notifications and audit trail...');
        $this->insertGenerated('notifications', self::TARGETS['notifications'], 500, function (int $i) use ($tenantId, $userIds, $today, $now): array {
            return [
                'tenant_id' => $tenantId,
                'recipient_user_id' => $userIds[$i % count($userIds)],
                'type' => ['TASK_ASSIGNED', 'TASK_DUE_SOON', 'CUSTODY_ASSIGNED', 'CONTRACT_EXPIRING'][$i % 4],
                'title' => 'إشعار أداء '.$i,
                'body' => null,
                'severity' => ['info', 'warning', 'critical'][$i % 3],
                'entity_type' => 'task',
                'entity_id' => ($i % self::TARGETS['tasks']) + 1,
                'dedupe_key' => null,
                'read_at' => $i % 5 === 0 ? null : $today->copy()->subDays($i % 90)->addMinutes($i % 1440)->format('Y-m-d H:i:s'),
                'correlation_id' => $i % 100 === 0 ? 'perf-correlation-'.$i : null,
                'created_at' => $today->copy()->subDays($i % 90)->addMinutes($i % 1440)->format('Y-m-d H:i:s'),
                'updated_at' => $now,
            ];
        });

        $this->insertGenerated('audit_logs', self::TARGETS['audit_logs'], 500, function (int $i) use ($tenantId, $ownerId, $today): array {
            return [
                'tenant_id' => $tenantId,
                'context_type' => 'tenant',
                'actor_type' => 'user',
                'actor_user_id' => $ownerId,
                'actor_label' => 'Performance Owner',
                'event_type' => ['TASK_COMPLETED', 'STOCK_ISSUED', 'ASSET_ASSIGNED', 'CONTRACT_APPROVED'][$i % 4],
                'entity_type' => ['task', 'inventory_item', 'asset', 'contract'][$i % 4],
                'entity_id' => ($i % 75000) + 1,
                'entity_number' => 'PERF-'.$i,
                'entity_label' => $i % 100 === 0 ? 'سجل بحث الحج '.$i : 'سجل أداء '.$i,
                'reason' => null,
                'metadata' => null,
                'before_values' => null,
                'after_values' => null,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'PerformanceFixture/1.0',
                'correlation_id' => 'perf-correlation-'.($i % 5000),
                'source' => 'console',
                'created_at' => $today->copy()->subDays($i % 90)->addMinutes($i % 1440)->format('Y-m-d H:i:s'),
            ];
        });

        return collect(array_keys(self::TARGETS))
            ->mapWithKeys(fn (string $table): array => [
                $table => DB::table($table)->where('tenant_id', $tenantId)->count(),
            ])
            ->all();
    }

    /**
     * @param  array<int, int>  $userIds
     * @return \Generator<int, array<string, mixed>>
     */
    private function organizationUnits(int $tenantId, array $userIds, string $now): \Generator
    {
        for ($i = 0; $i < self::TARGETS['organization_units']; $i++) {
            yield [
                'tenant_id' => $tenantId,
                'parent_id' => $i === 0 ? null : null,
                'name' => 'وحدة أداء '.$i,
                'code' => sprintf('PERF-%03d', $i + 1),
                'type' => $i % 4 === 0 ? 'department' : 'unit',
                'status' => 'active',
                'manager_user_id' => $userIds[$i % count($userIds)],
                'sort_order' => $i,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
    }

    /**
     * @return \Generator<int, array<string, mixed>>
     */
    private function users(int $tenantId, string $now): \Generator
    {
        $passwordHash = bcrypt('PerfFixture@123');

        for ($i = 0; $i < self::TARGETS['users'] - 1; $i++) {
            yield [
                'tenant_id' => $tenantId,
                'name' => 'مستخدم أداء '.$i,
                'email' => sprintf('user%04d@perf-large.invalid', $i + 1),
                'email_verified_at' => $now,
                'password' => $passwordHash,
                'remember_token' => null,
                'status' => 'active',
                'avatar_group' => 'neutral',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
    }

    /**
     * @return \Generator<int, array<string, mixed>>
     */
    private function positions(int $tenantId, string $now): \Generator
    {
        for ($i = 0; $i < 20; $i++) {
            yield [
                'tenant_id' => $tenantId,
                'name' => 'وظيفة أداء '.$i,
                'code' => sprintf('PERF-POS-%02d', $i + 1),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
    }

    /**
     * @param  array<int, int>  $userIds
     * @param  array<int, int>  $unitIds
     * @param  array<int, int>  $positionIds
     * @return \Generator<int, array<string, mixed>>
     */
    private function employees(int $tenantId, array $userIds, array $unitIds, array $positionIds, string $now): \Generator
    {
        for ($i = 0; $i < self::TARGETS['employees']; $i++) {
            yield [
                'tenant_id' => $tenantId,
                'user_id' => $i < count($userIds) ? $userIds[$i] : null,
                'employee_number' => sprintf('EMP-%06d', $i + 1),
                'full_name' => ($i % 100 === 0 ? 'موظف بحث الحج ' : 'موظف أداء ').$i,
                'phone' => sprintf('+96655%07d', $i),
                'email' => sprintf('employee%04d@perf-large.invalid', $i + 1),
                'organization_unit_id' => $unitIds[$i % count($unitIds)],
                'position_id' => $positionIds[$i % count($positionIds)],
                'supervisor_id' => null,
                'status' => 'active',
                'hire_date' => now()->subDays($i % 3000)->toDateString(),
                'notes' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
    }

    /**
     * @return \Generator<int, array<string, mixed>>
     */
    private function simpleCategories(string $kind, int $tenantId, string $now): \Generator
    {
        for ($i = 0; $i < 20; $i++) {
            $row = [
                'tenant_id' => $tenantId,
                'name' => 'فئة '.$kind.' '.$i,
                'description' => null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if ($kind === 'contract') {
                unset($row['description']);
                $row['code'] = sprintf('PERF-CTR-%02d', $i + 1);
            }
            yield $row;
        }
    }

    /**
     * @return array<int, int>
     */
    private function ids(string $table, int $tenantId): array
    {
        return DB::table($table)
            ->where('tenant_id', $tenantId)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @param  iterable<array<string, mixed>>  $rows
     */
    private function insertChunked(string $table, iterable $rows, int $chunkSize): void
    {
        $chunk = [];
        foreach ($rows as $row) {
            $chunk[] = $row;
            if (count($chunk) === $chunkSize) {
                DB::table($table)->insert($chunk);
                $chunk = [];
            }
        }
        if ($chunk !== []) {
            DB::table($table)->insert($chunk);
        }
    }

    /**
     * @param  callable(int): array<string, mixed>  $row
     */
    private function insertGenerated(string $table, int $count, int $chunkSize, callable $row): void
    {
        $chunk = [];
        for ($i = 0; $i < $count; $i++) {
            $chunk[] = $row($i);
            if (count($chunk) === $chunkSize) {
                DB::table($table)->insert($chunk);
                $chunk = [];
            }
        }
        if ($chunk !== []) {
            DB::table($table)->insert($chunk);
        }
    }

    private function dropDatabase(string $database): void
    {
        $pdo = $this->serverPdo();
        $pdo->exec(sprintf('DROP DATABASE IF EXISTS `%s`', $database));
    }

    private function ensureDatabase(string $database): void
    {
        $connection = config('database.connections.mysql');
        $charset = (string) ($connection['charset'] ?? 'utf8mb4');
        $collation = (string) ($connection['collation'] ?? 'utf8mb4_unicode_ci');
        $this->serverPdo()->exec(sprintf('CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET %s COLLATE %s', $database, $charset, $collation));
    }

    private function serverPdo(): PDO
    {
        $connection = config('database.connections.mysql');

        return new PDO(
            sprintf('mysql:host=%s;port=%s', $connection['host'], $connection['port'] ?? 3306),
            $connection['username'],
            $connection['password'] ?? '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
    }

    private function switchDatabase(string $database): void
    {
        config(['database.connections.mysql.database' => $database]);
        DB::purge('mysql');
        DB::reconnect('mysql');
        DB::setDefaultConnection('mysql');
    }
}
