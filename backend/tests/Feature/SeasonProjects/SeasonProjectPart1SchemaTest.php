<?php

use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonProjects\Exceptions\SeasonProjectDomainException;
use App\Modules\SeasonProjects\Models\SeasonProject;
use App\Modules\SeasonProjects\Models\SeasonProjectBudgetPoLink;
use App\Modules\SeasonProjects\Models\SeasonProjectPhase;
use App\Modules\SeasonProjects\Support\SeasonProjectNumberGenerator;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseOrderStatus;
use App\Modules\SuppliersAndProcurement\Models\ProcurementRfq;
use App\Modules\SuppliersAndProcurement\Models\PurchaseOrder;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequest;
use App\Modules\Tasks\Enums\TaskDelayState;
use App\Modules\Tasks\Enums\TaskStatus;
use App\Modules\Tasks\Models\Task;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * Insert a minimal S05 PO header for linkage uniqueness tests (no PO workflow).
 * Avoids Supplier::factory numbering (MySQL upsert) so sqlite Feature tests stay isolated.
 */
function createMinimalPurchaseOrderForSeasonProjectTests(Tenant $tenant): PurchaseOrder
{
    return withTenant($tenant, function () use ($tenant): PurchaseOrder {
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $pr = PurchaseRequest::factory()->create([
            'requested_by' => $user->id,
        ]);
        $rfq = ProcurementRfq::factory()->create([
            'purchase_request_id' => $pr->id,
            'created_by' => $user->id,
        ]);

        $supplierId = DB::table('suppliers')->insertGetId([
            'tenant_id' => $tenant->id,
            'supplier_number' => 'SUP-'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'supplier_type' => 'company',
            'status' => 'draft',
            'display_name' => 'مورد اختبار',
            'legal_name' => 'مورد اختبار',
            'country_code' => 'SA',
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $id = DB::table('purchase_orders')->insertGetId([
            'tenant_id' => $tenant->id,
            'po_number' => 'PO-'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'procurement_rfq_id' => $rfq->id,
            'purchase_request_id' => $pr->id,
            'supplier_id' => $supplierId,
            'supplier_offer_id' => null,
            'contract_id' => null,
            'season_id' => null,
            'status' => PurchaseOrderStatus::Issued->value,
            'currency' => 'SAR',
            'notes' => null,
            'issued_at' => now(),
            'issued_by' => $user->id,
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return PurchaseOrder::query()->findOrFail($id);
    });
}

test('Part1 migrations create season project tables and extend tasks', function (): void {
    expect(Schema::hasTable('season_project_number_sequences'))->toBeTrue()
        ->and(Schema::hasTable('season_projects'))->toBeTrue()
        ->and(Schema::hasTable('season_project_phases'))->toBeTrue()
        ->and(Schema::hasTable('season_project_budget_po_links'))->toBeTrue()
        ->and(Schema::hasColumns('tasks', [
            'season_id',
            'project_id',
            'phase_id',
            'delay_state',
            'delay_calculated_at',
        ]))->toBeTrue();
});

test('Part1 MVP tasks remain valid with null project and season fields', function (): void {
    $tenant = Tenant::factory()->create();

    $task = withTenant($tenant, fn () => Task::factory()->create([
        'title' => 'مهمة إرث',
    ]));

    expect($task->season_id)->toBeNull()
        ->and($task->project_id)->toBeNull()
        ->and($task->phase_id)->toBeNull()
        ->and($task->delay_state)->toBe(TaskDelayState::None)
        ->and($task->status)->toBe(TaskStatus::Draft)
        ->and($task->task_number)->toStartWith('TSK-');
});

test('Part1 project numbers allocate per tenant and season and never reuse after soft delete', function (): void {
    $tenant = Tenant::factory()->create();

    [$seasonA, $first, $second, $otherSeason, $softDeletedNumber, $third] = withTenant($tenant, function () {
        $seasonA = Season::factory()->create();
        $seasonB = Season::factory()->create();

        $first = SeasonProject::factory()->create([
            'season_id' => $seasonA->id,
            'title' => 'مشروع 1',
        ]);
        $second = SeasonProject::factory()->create([
            'season_id' => $seasonA->id,
            'title' => 'مشروع 2',
        ]);
        $otherSeason = SeasonProject::factory()->create([
            'season_id' => $seasonB->id,
            'title' => 'مشروع موسم آخر',
        ]);

        $softDeletedNumber = $first->number;
        $first->delete();

        $third = SeasonProject::factory()->create([
            'season_id' => $seasonA->id,
            'title' => 'مشروع بعد حذف',
        ]);

        return [$seasonA, $first, $second, $otherSeason, $softDeletedNumber, $third];
    });

    expect($first->number)->toBe('SPJ-000001')
        ->and($second->number)->toBe('SPJ-000002')
        ->and($otherSeason->number)->toBe('SPJ-000001')
        ->and($softDeletedNumber)->toBe('SPJ-000001')
        ->and($third->number)->toBe('SPJ-000003')
        ->and($third->number)->not->toBe($softDeletedNumber);

    expect(fn () => withTenant($tenant, function () use ($seasonA, $softDeletedNumber): void {
        SeasonProject::factory()->create([
            'season_id' => $seasonA->id,
            'number' => $softDeletedNumber,
            'title' => 'إعادة استخدام ممنوعة',
        ]);
    }))->toThrow(QueryException::class);
});

test('Part1 generator is sequential under lock for same season', function (): void {
    $tenant = Tenant::factory()->create();

    $numbers = withTenant($tenant, function () {
        $season = Season::factory()->create();
        $generator = app(SeasonProjectNumberGenerator::class);

        return DB::transaction(function () use ($generator, $season) {
            return [
                $generator->next($season->id),
                $generator->next($season->id),
                $generator->next($season->id),
            ];
        });
    });

    expect($numbers)->toBe(['SPJ-000001', 'SPJ-000002', 'SPJ-000003']);
});

test('Part1 soft delete allows draft only and rejects dependents', function (): void {
    $tenant = Tenant::factory()->create();

    withTenant($tenant, function () {
        $draft = SeasonProject::factory()->draft()->create();
        expect($draft->delete())->toBeTrue();
        expect(SeasonProject::withTrashed()->find($draft->id)?->trashed())->toBeTrue();

        foreach ([
            SeasonProject::factory()->active(),
            SeasonProject::factory()->completed(),
            SeasonProject::factory()->cancelled(),
        ] as $factory) {
            $project = $factory->create();
            expect(fn () => $project->delete())
                ->toThrow(SeasonProjectDomainException::class);
        }

        $withTask = SeasonProject::factory()->draft()->create();
        Task::factory()->create([
            'season_id' => $withTask->season_id,
            'project_id' => $withTask->id,
            'title' => 'مهمة مرتبطة',
        ]);
        expect(fn () => $withTask->delete())
            ->toThrow(SeasonProjectDomainException::class);

        $withLink = SeasonProject::factory()->draft()->create();
        $po = createMinimalPurchaseOrderForSeasonProjectTests(
            Tenant::query()->findOrFail($withLink->tenant_id)
        );
        SeasonProjectBudgetPoLink::factory()->create([
            'project_id' => $withLink->id,
            'season_id' => $withLink->season_id,
            'purchase_order_id' => $po->id,
            'linked_by' => User::factory()->create(['tenant_id' => $withLink->tenant_id])->id,
        ]);
        expect(fn () => $withLink->delete())
            ->toThrow(SeasonProjectDomainException::class);
    });
});

test('Part1 PO link unique per tenant prevents double counting', function (): void {
    $tenant = Tenant::factory()->create();

    withTenant($tenant, function () use ($tenant): void {
        $projectA = SeasonProject::factory()->create();
        $projectB = SeasonProject::factory()->create([
            'season_id' => $projectA->season_id,
        ]);
        $po = createMinimalPurchaseOrderForSeasonProjectTests($tenant);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        SeasonProjectBudgetPoLink::factory()->create([
            'project_id' => $projectA->id,
            'season_id' => $projectA->season_id,
            'purchase_order_id' => $po->id,
            'linked_by' => $user->id,
        ]);

        expect(fn () => SeasonProjectBudgetPoLink::factory()->create([
            'project_id' => $projectB->id,
            'season_id' => $projectB->season_id,
            'purchase_order_id' => $po->id,
            'linked_by' => $user->id,
        ]))->toThrow(QueryException::class);
    });
});

test('Part1 phases and projects are tenant scoped', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $projectA = withTenant($tenantA, fn () => SeasonProject::factory()->create());
    withTenant($tenantB, fn () => SeasonProject::factory()->create());

    $visible = withTenant($tenantA, fn () => SeasonProject::query()->pluck('id')->all());
    expect($visible)->toBe([$projectA->id]);

    withTenant($tenantA, function () use ($projectA): void {
        SeasonProjectPhase::factory()->create([
            'project_id' => $projectA->id,
            'season_id' => $projectA->season_id,
            'sort_order' => 1,
        ]);
    });

    $phaseCountB = withTenant($tenantB, fn () => SeasonProjectPhase::query()->count());
    expect($phaseCountB)->toBe(0);
});

test('Part1 cross-tenant season FK is rejected', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $seasonB = withTenant($tenantB, fn () => Season::factory()->create());

    // Sequence/project composite FKs reject cross-tenant seasons; generator may wrap as domain exception.
    $thrown = null;
    try {
        withTenant($tenantA, function () use ($seasonB): void {
            SeasonProject::factory()->create([
                'season_id' => $seasonB->id,
                'title' => 'موسم مستأجر آخر',
            ]);
        });
    } catch (Throwable $e) {
        $thrown = $e;
    }

    expect($thrown)->not->toBeNull()
        ->and(
            $thrown instanceof QueryException
            || $thrown instanceof SeasonProjectDomainException
        )->toBeTrue();
});

test('Part2/3 registers season-projects CRUD lifecycle and phase routes without restore reopen', function (): void {
    $uris = collect(Route::getRoutes())
        ->map(fn ($route) => $route->uri())
        ->filter(fn (string $uri) => str_contains($uri, 'season-project'))
        ->values()
        ->all();

    expect($uris)->toContain('api/v1/season-projects')
        ->and($uris)->toContain('api/v1/season-projects/{season_project}/activate')
        ->and($uris)->toContain('api/v1/season-projects/{season_project}/complete')
        ->and($uris)->toContain('api/v1/season-projects/{season_project}/cancel')
        ->and($uris)->toContain('api/v1/season-projects/{season_project}/reassign-owner')
        ->and($uris)->toContain('api/v1/season-projects/{season_project}/phases')
        ->and($uris)->toContain('api/v1/season-projects/{season_project}/budget')
        ->and(collect($uris)->first(fn (string $uri) => str_contains($uri, 'restore')))->toBeNull()
        ->and(collect($uris)->first(fn (string $uri) => str_contains($uri, 'reopen')))->toBeNull();
});

test('Part1 rollback drops season project schema and task extensions', function (): void {
    if (DB::connection()->getDriverName() === 'sqlite') {
        // SQLite cannot drop FK-backed columns by rebuild in this alter; verify down() on MySQL 8.
        test()->markTestSkipped('Part1 migration rollback of tasks FK columns requires MySQL/MariaDB (phpunit.mysql.xml).');
    }

    expect(Artisan::call('migrate:rollback', ['--step' => 5, '--force' => true]))->toBe(0);

    expect(Schema::hasTable('season_projects'))->toBeFalse()
        ->and(Schema::hasTable('season_project_phases'))->toBeFalse()
        ->and(Schema::hasTable('season_project_budget_po_links'))->toBeFalse()
        ->and(Schema::hasTable('season_project_number_sequences'))->toBeFalse()
        ->and(Schema::hasColumn('tasks', 'project_id'))->toBeFalse();

    expect(Artisan::call('migrate', ['--force' => true]))->toBe(0);
    expect(Schema::hasTable('season_projects'))->toBeTrue()
        ->and(Schema::hasColumn('tasks', 'project_id'))->toBeTrue();
});
