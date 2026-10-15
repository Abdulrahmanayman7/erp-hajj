<?php

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantContext;
use App\Modules\SeasonPilgrims\Context\RestoreSeasonContext;
use App\Modules\SeasonPilgrims\Context\SeasonAware;
use App\Modules\SeasonPilgrims\Context\SeasonContext;
use App\Modules\SeasonPilgrims\Exceptions\SeasonPilgrimDomainException;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrim;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\Job as QueueJobContract;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Queue;

class FakeSeasonAwareJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SeasonAware;

    public static ?int $observedSeasonId = null;

    public static ?int $observedTenantId = null;

    public function __construct()
    {
        $this->captureSeasonContext();
    }

    public function handle(): void
    {
        self::$observedSeasonId = app(SeasonContext::class)->id();
        self::$observedTenantId = app(TenantContext::class)->id();
    }
}

it('defaults to the tenant Active season when no season is requested', function (): void {
    actingAsTenantOwner();
    spCreateSeason();
    $active = spActiveSeason();

    spaGetJson('/api/v1/season-context')
        ->assertOk()
        ->assertJsonPath('data.season.id', $active['id'])
        ->assertJsonPath('data.selection', 'default_active')
        ->assertJsonPath('data.is_writable', true);
});

it('fails with a stable code when there is no Active season and none is requested', function (): void {
    actingAsTenantOwner();
    spCreateSeason();

    spaGetJson('/api/v1/season-context')
        ->assertStatus(422)
        ->assertJsonPath('code', 'SEASON_CONTEXT_MISSING');
    spaGetJson('/api/v1/season-pilgrims')
        ->assertStatus(422)
        ->assertJsonPath('code', 'SEASON_CONTEXT_MISSING');
});

it('selects an explicit Draft season for preparation', function (): void {
    actingAsTenantOwner();
    spActiveSeason();
    $draft = spCreateSeason();

    spaGetJson('/api/v1/season-context', seasonHeader($draft['id']))
        ->assertOk()
        ->assertJsonPath('data.season.id', $draft['id'])
        ->assertJsonPath('data.season.status', 'draft')
        ->assertJsonPath('data.selection', 'explicit')
        ->assertJsonPath('data.is_writable', true);

    $sp = spRegister($draft['id'], spCreatePilgrim()['id']);
    expect($sp['season_id'])->toBe($draft['id']);
});

it('allows reading a Closed season but blocks writes', function (): void {
    actingAsTenantOwner();
    $season = spActiveSeason();
    $group = spCreateGroup($season['id']);
    spaPostJson("/api/v1/seasons/{$season['id']}/close")->assertOk();
    $header = seasonHeader($season['id']);

    spaGetJson('/api/v1/season-context', $header)
        ->assertOk()
        ->assertJsonPath('data.season.status', 'closed')
        ->assertJsonPath('data.is_writable', false);
    spaGetJson('/api/v1/season-pilgrim-groups', $header)->assertOk()->assertJsonCount(1, 'data');
    spaGetJson('/api/v1/season-programs', $header)->assertOk();
    spaGetJson('/api/v1/season-family-groups', $header)->assertOk();

    spaPostJson('/api/v1/season-pilgrim-groups', ['code' => 'NEW', 'name' => 'جديدة'], $header)
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_CLOSED');
    spaPatchJson("/api/v1/season-pilgrim-groups/{$group['id']}", ['name' => 'x'], $header)
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_CLOSED');
    spaPostJson('/api/v1/season-programs', ['code' => 'P', 'name' => 'ب'], $header)
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_CLOSED');
    spaPostJson('/api/v1/season-family-groups', ['name' => 'ع'], $header)
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_CLOSED');
});

it('rejects a malformed season context header', function (): void {
    actingAsTenantOwner();
    spActiveSeason();

    foreach (['abc', '-1', '0', '1.5', '1e3', '12abc'] as $value) {
        spaGetJson('/api/v1/season-context', ['X-Season-Id' => $value])
            ->assertStatus(422)->assertJsonPath('code', 'SEASON_CONTEXT_INVALID');
    }
});

it('gives an explicit header precedence over the Active season', function (): void {
    actingAsTenantOwner();
    $closed = spActiveSeason();
    spaPostJson("/api/v1/seasons/{$closed['id']}/close")->assertOk();
    $active = spActiveSeason();

    spaGetJson('/api/v1/season-context', seasonHeader($closed['id']))
        ->assertOk()
        ->assertJsonPath('data.season.id', $closed['id'])
        ->assertJsonPath('data.selection', 'explicit')
        ->assertJsonPath('data.is_writable', false);

    test()->flushHeaders();

    spaGetJson('/api/v1/season-context')
        ->assertOk()
        ->assertJsonPath('data.season.id', $active['id'])
        ->assertJsonPath('data.selection', 'default_active');
});

it('resolves deterministically to SEASON_CONTEXT_MISSING when only Closed seasons exist', function (): void {
    actingAsTenantOwner();
    $season = spActiveSeason();
    spaPostJson("/api/v1/seasons/{$season['id']}/close")->assertOk();

    spaGetJson('/api/v1/season-context')->assertStatus(422)->assertJsonPath('code', 'SEASON_CONTEXT_MISSING');
    spaGetJson('/api/v1/season-context', seasonHeader($season['id']))->assertOk();
});

it('clears the season context even when the request fails', function (): void {
    actingAsTenantOwner();
    $season = spActiveSeason();
    spaPostJson("/api/v1/seasons/{$season['id']}/close")->assertOk();

    spaPostJson('/api/v1/season-programs', ['code' => 'X', 'name' => 'x'], seasonHeader($season['id']))
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_CLOSED');
    expect(app(SeasonContext::class)->has())->toBeFalse();

    spaGetJson('/api/v1/season-pilgrims/999999', seasonHeader($season['id']))->assertNotFound();
    expect(app(SeasonContext::class)->has())->toBeFalse();
});

it("returns 404 for another tenant's season or an unknown season", function (): void {
    actingAsTenantOwner();
    $foreign = spActiveSeason();

    actingAsTenantOwner();
    spActiveSeason();

    spaGetJson('/api/v1/season-context', seasonHeader($foreign['id']))->assertNotFound();
    spaGetJson('/api/v1/season-pilgrims', seasonHeader($foreign['id']))->assertNotFound();
    spaPostJson('/api/v1/season-pilgrim-groups', ['code' => 'X', 'name' => 'x'], seasonHeader($foreign['id']))->assertNotFound();
    spaGetJson('/api/v1/season-context', seasonHeader(999999))->assertNotFound();
});

it('cannot bypass tenancy or the context through payload fields', function (): void {
    $ownerA = actingAsTenantOwner();
    $foreign = spActiveSeason();

    $ownerB = actingAsTenantOwner();
    $own = spActiveSeason();
    $draft = spCreateSeason();
    $pilgrim = spCreatePilgrim();

    $sp = spaPostJson('/api/v1/season-pilgrims', [
        'pilgrim_id' => $pilgrim['id'],
        'season_id' => $foreign['id'],
        'tenant_id' => $ownerA->tenant_id,
    ], seasonHeader($own['id']))->assertCreated()->json('data');

    expect($sp['season_id'])->toBe($own['id']);

    $stored = SeasonPilgrim::query()->withoutGlobalScopes()->find($sp['id']);
    expect($stored->tenant_id)->toBe($ownerB->tenant_id)
        ->and($stored->season_id)->toBe($own['id']);

    // A record of the Active season is invisible when another season is the context.
    spaGetJson("/api/v1/season-pilgrims/{$sp['id']}", seasonHeader($draft['id']))->assertNotFound();
});

it('clears the season context after each request', function (): void {
    actingAsTenantOwner();
    spActiveSeason();

    spaGetJson('/api/v1/season-context')->assertOk();

    expect(app(SeasonContext::class)->has())->toBeFalse();
});

it('refuses to set a season that belongs to another tenant than the tenant context', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $seasonA = withTenant($tenantA, fn () => Season::factory()->create());

    withTenant($tenantB, function () use ($seasonA): void {
        expect(fn () => app(SeasonContext::class)->set($seasonA, true))->toThrow(LogicException::class);
    });
});

it('requires a season context and fails closed without one', function (): void {
    expect(fn () => app(SeasonContext::class)->require())->toThrow(SeasonPilgrimDomainException::class);
});

it('restores tenant and season context in season-aware jobs', function (): void {
    FakeSeasonAwareJob::$observedSeasonId = null;
    FakeSeasonAwareJob::$observedTenantId = null;
    $tenant = Tenant::factory()->create();

    $season = withTenant($tenant, function (): Season {
        $season = Season::factory()->create();
        app(SeasonContext::class)->runInSeason($season, fn () => FakeSeasonAwareJob::dispatch());

        return $season;
    });

    expect(FakeSeasonAwareJob::$observedSeasonId)->toBe($season->id)
        ->and(FakeSeasonAwareJob::$observedTenantId)->toBe($tenant->id)
        ->and(app(SeasonContext::class)->has())->toBeFalse();
});

it('captures the season id as server-generated job metadata', function (): void {
    Queue::fake();
    $tenant = Tenant::factory()->create();

    $season = withTenant($tenant, function (): Season {
        $season = Season::factory()->create();
        app(SeasonContext::class)->runInSeason($season, fn () => FakeSeasonAwareJob::dispatch());

        return $season;
    });

    Queue::assertPushed(
        FakeSeasonAwareJob::class,
        fn (FakeSeasonAwareJob $job): bool => $job->seasonId === $season->id && $job->tenantId === $tenant->id,
    );
});

it('restores each job into its own tenant and season when tenants interleave', function (): void {
    FakeSeasonAwareJob::$observedSeasonId = null;
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    [$jobA, $seasonA] = withTenant($tenantA, function (): array {
        $season = Season::factory()->create();

        return [app(SeasonContext::class)->runInSeason($season, fn () => new FakeSeasonAwareJob), $season];
    });
    [$jobB, $seasonB] = withTenant($tenantB, function (): array {
        $season = Season::factory()->create();

        return [app(SeasonContext::class)->runInSeason($season, fn () => new FakeSeasonAwareJob), $season];
    });

    $run = function (FakeSeasonAwareJob $job): void {
        (new Pipeline(app()))->send($job)->through($job->middleware())->then(fn ($j) => $j->handle());
    };

    $run($jobB);
    expect(FakeSeasonAwareJob::$observedTenantId)->toBe($tenantB->id)
        ->and(FakeSeasonAwareJob::$observedSeasonId)->toBe($seasonB->id);

    $run($jobA);
    expect(FakeSeasonAwareJob::$observedTenantId)->toBe($tenantA->id)
        ->and(FakeSeasonAwareJob::$observedSeasonId)->toBe($seasonA->id)
        ->and(app(SeasonContext::class)->has())->toBeFalse()
        ->and(app(TenantContext::class)->has())->toBeFalse();
});

it('fails a season-aware job whose season belongs to another tenant', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $foreignSeason = withTenant($tenantB, fn () => Season::factory()->create());

    $job = withTenant($tenantA, function (): FakeSeasonAwareJob {
        $season = Season::factory()->create();

        return app(SeasonContext::class)->runInSeason($season, fn () => new FakeSeasonAwareJob);
    });
    $job->seasonId = $foreignSeason->id;

    $queueJob = Mockery::mock(QueueJobContract::class);
    $queueJob->shouldReceive('fail')->once();
    $job->setJob($queueJob);

    withTenant($tenantA, function () use ($job): void {
        (new RestoreSeasonContext)->handle($job, fn () => throw new RuntimeException('must not run'));
    });

    expect(app(SeasonContext::class)->has())->toBeFalse();
});

it('fails a season-aware job whose season no longer exists', function (): void {
    $tenant = Tenant::factory()->create();

    $job = withTenant($tenant, function (): FakeSeasonAwareJob {
        $season = Season::factory()->create();

        return app(SeasonContext::class)->runInSeason($season, fn () => new FakeSeasonAwareJob);
    });
    $job->seasonId = 999999;

    $queueJob = Mockery::mock(QueueJobContract::class);
    $queueJob->shouldReceive('fail')->once();
    $job->setJob($queueJob);

    withTenant($tenant, function () use ($job): void {
        (new RestoreSeasonContext)->handle($job, fn () => throw new RuntimeException('must not run'));
    });
});

it('fails dispatching a season-aware job without season context', function (): void {
    $tenant = Tenant::factory()->create();

    withTenant($tenant, fn () => FakeSeasonAwareJob::dispatch());
})->throws(SeasonPilgrimDomainException::class);
