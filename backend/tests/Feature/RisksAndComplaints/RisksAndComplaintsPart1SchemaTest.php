<?php

use App\Core\Tenancy\Exceptions\MissingTenantContextException;
use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use App\Modules\Employees\Models\Employee;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use App\Modules\RisksAndComplaints\Enums\ComplaintStatus;
use App\Modules\RisksAndComplaints\Enums\RiskSeverity;
use App\Modules\RisksAndComplaints\Enums\RiskStatus;
use App\Modules\RisksAndComplaints\Enums\TreatmentStatus;
use App\Modules\RisksAndComplaints\Exceptions\RisksAndComplaintsDomainException;
use App\Modules\RisksAndComplaints\Models\OperationalComplaint;
use App\Modules\RisksAndComplaints\Models\OperationalComplaintNote;
use App\Modules\RisksAndComplaints\Models\OperationalRisk;
use App\Modules\RisksAndComplaints\Models\OperationalRiskTreatment;
use App\Modules\RisksAndComplaints\Support\OperationalComplaintNumberGenerator;
use App\Modules\RisksAndComplaints\Support\OperationalRiskNumberGenerator;
use App\Modules\RisksAndComplaints\Support\RiskScoreCalculator;
use App\Modules\SeasonPilgrims\Models\Season;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * @return list<string>
 */
function s07UniqueColumnSets(string $table): array
{
    return collect(Schema::getIndexes($table))
        ->where('unique', true)
        ->pluck('columns')
        ->map(fn (array $columns): string => implode(',', $columns))
        ->values()
        ->all();
}

it('creates S07 Part 1 tables with tenant_id where required', function (): void {
    $tables = [
        'operational_risk_number_sequences',
        'operational_complaint_number_sequences',
        'operational_risks',
        'operational_risk_treatments',
        'operational_complaints',
        'operational_complaint_notes',
    ];

    foreach ($tables as $table) {
        expect(Schema::hasTable($table))->toBeTrue("missing {$table}")
            ->and(Schema::hasColumn($table, 'tenant_id'))->toBeTrue("missing tenant_id on {$table}");
    }

    expect(Schema::hasColumn('operational_risks', 'deleted_at'))->toBeTrue()
        ->and(Schema::hasColumn('operational_complaints', 'deleted_at'))->toBeTrue()
        ->and(Schema::hasColumn('operational_risks', 'project_id'))->toBeTrue()
        ->and(Schema::hasColumn('operational_complaints', 'project_id'))->toBeTrue()
        ->and(Schema::hasColumn('operational_risks', 'transport_season_vehicle_id'))->toBeTrue()
        ->and(Schema::hasColumn('operational_risk_treatments', 'due_at'))->toBeTrue();
});

it('declares expected unique indexes for numbers and tenant-id composites', function (): void {
    expect(s07UniqueColumnSets('operational_risks'))->toContain(
        'tenant_id,id',
        'tenant_id,season_id,risk_number',
    );
    expect(s07UniqueColumnSets('operational_complaints'))->toContain(
        'tenant_id,id',
        'tenant_id,season_id,complaint_number',
    );
    expect(s07UniqueColumnSets('operational_risk_treatments'))->toContain('risk_id');
});

it('does not declare a project_id foreign key (S06 optional / D08)', function (): void {
    $riskFks = collect(Schema::getForeignKeys('operational_risks'))
        ->flatMap(fn (array $fk): array => $fk['columns'])
        ->all();
    $complaintFks = collect(Schema::getForeignKeys('operational_complaints'))
        ->flatMap(fn (array $fk): array => $fk['columns'])
        ->all();

    expect($riskFks)->not->toContain('project_id')
        ->and($complaintFks)->not->toContain('project_id');
});

it('exposes provisional complaint category codes in config', function (): void {
    expect(config('risks_complaints.complaint_categories'))->toBe([
        'service',
        'accommodation',
        'transport',
        'procurement',
        'staffing',
        'safety',
        'other',
    ]);
});

it('calculates severity bands per D02', function (): void {
    $calc = app(RiskScoreCalculator::class);

    expect($calc->calculate(1, 1))->toMatchArray(['score' => 1, 'severity' => RiskSeverity::Low])
        ->and($calc->calculate(2, 2))->toMatchArray(['score' => 4, 'severity' => RiskSeverity::Low])
        ->and($calc->calculate(3, 2))->toMatchArray(['score' => 6, 'severity' => RiskSeverity::Medium])
        ->and($calc->calculate(5, 2))->toMatchArray(['score' => 10, 'severity' => RiskSeverity::High])
        ->and($calc->calculate(5, 4))->toMatchArray(['score' => 20, 'severity' => RiskSeverity::Critical]);

    expect(fn () => $calc->calculate(0, 3))
        ->toThrow(RisksAndComplaintsDomainException::class);
});

it('creates risks and complaints via factories with default states', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = Tenant::query()->findOrFail($owner->tenant_id);

    withTenant($tenant, function () use ($tenant): void {
        $season = Season::factory()->create();
        $risk = OperationalRisk::factory()->forSeason($season)->create();
        $complaint = OperationalComplaint::factory()->forSeason($season)->create();

        expect($risk->status)->toBe(RiskStatus::Identified)
            ->and($risk->tenant_id)->toBe($tenant->id)
            ->and($risk->season_id)->toBe($season->id)
            ->and($risk->risk_number)->toMatch('/^RSK-\d{4}-\d{6}$/')
            ->and($complaint->status)->toBe(ComplaintStatus::New)
            ->and($complaint->complaint_number)->toMatch('/^CMP-\d{4}-\d{6}$/')
            ->and($complaint->project_id)->toBeNull()
            ->and($risk->project_id)->toBeNull();
    });
});

it('allows nullable project_id without SeasonProjects FK', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = Tenant::query()->findOrFail($owner->tenant_id);

    withTenant($tenant, function (): void {
        $season = Season::factory()->create();
        $risk = OperationalRisk::factory()->forSeason($season)->create([
            'project_id' => 999001,
        ]);

        expect($risk->fresh()->project_id)->toBe(999001);
    });
});

it('allocates sequential numbers under lock without reuse after soft-delete', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = Tenant::query()->findOrFail($owner->tenant_id);

    withTenant($tenant, function (): void {
        $season = Season::factory()->create();
        $year = (int) now()->year;

        $first = app(OperationalRiskNumberGenerator::class)->next($season, $year);
        $second = app(OperationalRiskNumberGenerator::class)->next($season, $year);
        expect($first)->not->toBe($second)
            ->and($first)->toEndWith('000001')
            ->and($second)->toEndWith('000002');

        $risk = OperationalRisk::factory()->forSeason($season)->create();
        $allocatedOnCreate = $risk->risk_number;
        $risk->delete();

        $third = app(OperationalRiskNumberGenerator::class)->next($season, $year);
        // create() consumed one sequence value after $second; soft-delete must not rewind.
        expect($allocatedOnCreate)->not->toBe($first)
            ->and($allocatedOnCreate)->not->toBe($second)
            ->and($third)->toEndWith('000004')
            ->and(OperationalRisk::withTrashed()->whereKey($risk->id)->exists())->toBeTrue();

        $c1 = app(OperationalComplaintNumberGenerator::class)->next($season, $year);
        $c2 = app(OperationalComplaintNumberGenerator::class)->next($season, $year);
        expect($c1)->toStartWith('CMP-'.$year.'-')
            ->and($c2)->not->toBe($c1);
    });
});

it('recalculates score and severity when likelihood and impact are set', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = Tenant::query()->findOrFail($owner->tenant_id);

    withTenant($tenant, function (): void {
        $season = Season::factory()->create();
        $risk = OperationalRisk::factory()->forSeason($season)->underAssessment(4, 4)->create();

        expect($risk->score)->toBe(16)
            ->and($risk->severity_level)->toBe(RiskSeverity::Critical);
    });
});

it('soft-deletes identified risks and rejects delete after assessment', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = Tenant::query()->findOrFail($owner->tenant_id);

    withTenant($tenant, function (): void {
        $season = Season::factory()->create();
        $risk = OperationalRisk::factory()->forSeason($season)->create();
        $risk->delete();
        expect(OperationalRisk::withTrashed()->find($risk->id)?->trashed())->toBeTrue();

        $assessed = OperationalRisk::factory()->forSeason($season)->underAssessment()->create();
        expect(fn () => $assessed->delete())
            ->toThrow(RisksAndComplaintsDomainException::class);
    });
});

it('soft-deletes new complaints and rejects delete after assignment', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = Tenant::query()->findOrFail($owner->tenant_id);

    withTenant($tenant, function () use ($tenant): void {
        $season = Season::factory()->create();
        $complaint = OperationalComplaint::factory()->forSeason($season)->create();
        $complaint->delete();
        expect(OperationalComplaint::withTrashed()->find($complaint->id)?->trashed())->toBeTrue();

        $unit = OrganizationUnit::factory()->create();
        $employee = Employee::factory()->create(['organization_unit_id' => $unit->id]);
        $assigned = OperationalComplaint::factory()->forSeason($season)->create([
            'status' => ComplaintStatus::New,
            'assignee_employee_id' => $employee->id,
        ]);
        expect(fn () => $assigned->delete())
            ->toThrow(RisksAndComplaintsDomainException::class);

        expect($tenant->id)->toBeInt();
    });
});

it('creates treatment and notes relationships', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = Tenant::query()->findOrFail($owner->tenant_id);

    withTenant($tenant, function (): void {
        $season = Season::factory()->create();
        $risk = OperationalRisk::factory()->forSeason($season)->create();
        $treatment = OperationalRiskTreatment::factory()->create([
            'risk_id' => $risk->id,
            'status' => TreatmentStatus::Planned,
        ]);
        expect($risk->fresh()->treatment?->id)->toBe($treatment->id)
            ->and($treatment->due_at)->toBeNull();

        $complaint = OperationalComplaint::factory()->forSeason($season)->create();
        $note = OperationalComplaintNote::factory()->create([
            'complaint_id' => $complaint->id,
        ]);
        expect($complaint->notes()->count())->toBe(1)
            ->and($note->body)->not->toBe('');
    });
});

it('fails closed when querying tenant-owned models without tenant context', function (): void {
    expect(fn () => OperationalRisk::query()->count())
        ->toThrow(MissingTenantContextException::class)
        ->and(fn () => OperationalComplaint::query()->count())
        ->toThrow(MissingTenantContextException::class);
});

it('scopes risks to the active tenant context', function (): void {
    $ownerA = actingAsTenantOwner();
    $tenantA = Tenant::query()->findOrFail($ownerA->tenant_id);
    $riskAId = withTenant($tenantA, function (): int {
        $season = Season::factory()->create();

        return OperationalRisk::factory()->forSeason($season)->create()->id;
    });

    $ownerB = actingAsTenantOwner();
    $tenantB = Tenant::query()->findOrFail($ownerB->tenant_id);

    withTenant($tenantB, function () use ($riskAId): void {
        expect(OperationalRisk::query()->whereKey($riskAId)->exists())->toBeFalse();
    });

    withTenant($tenantA, function () use ($riskAId): void {
        expect(OperationalRisk::query()->whereKey($riskAId)->exists())->toBeTrue();
    });
});

it('overwrites client-supplied tenant_id from context on create', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = Tenant::query()->findOrFail($owner->tenant_id);
    $other = Tenant::factory()->create();

    withTenant($tenant, function () use ($tenant, $other): void {
        $season = Season::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $risk = new OperationalRisk([
            'season_id' => $season->id,
            'title' => 'عزل المستأجر',
            'created_by' => $user->id,
        ]);
        $risk->setAttribute('tenant_id', $other->id);
        $risk->save();

        expect($risk->fresh()->tenant_id)->toBe($tenant->id)
            ->and($risk->fresh()->tenant_id)->not->toBe($other->id);
    });
});

it('stores sequence rows keyed by tenant season and year', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = Tenant::query()->findOrFail($owner->tenant_id);

    withTenant($tenant, function () use ($tenant): void {
        $season = Season::factory()->create();
        $year = (int) now()->year;
        app(OperationalRiskNumberGenerator::class)->next($season, $year);

        $row = DB::table('operational_risk_number_sequences')->where([
            'tenant_id' => $tenant->id,
            'season_id' => $season->id,
            'year' => $year,
        ])->first();

        expect($row)->not->toBeNull()
            ->and((int) $row->next_number)->toBe(2);
    });
});
