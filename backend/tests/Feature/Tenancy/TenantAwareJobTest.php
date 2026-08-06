<?php

use App\Core\Tenancy\Exceptions\InvalidTenantContextException;
use App\Core\Tenancy\Exceptions\MissingTenantContextException;
use App\Core\Tenancy\Jobs\RestoreTenantContext;
use App\Core\Tenancy\Jobs\TenantAware;
use App\Core\Tenancy\Jobs\TenantMaintenanceJob;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantContext;
use App\Core\Tenancy\TenantStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\Job as QueueJobContract;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Queue;

class FakeTenantBusinessJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use TenantAware;

    public static ?int $observedTenantId = null;

    public static int $runs = 0;

    public function __construct()
    {
        $this->captureTenantContext();
    }

    public function handle(): void
    {
        self::$observedTenantId = app(TenantContext::class)->id();
        self::$runs++;
    }
}

class FakeTenantMaintenanceJob implements ShouldQueue, TenantMaintenanceJob
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use TenantAware;

    public static int $runs = 0;

    public function __construct()
    {
        $this->captureTenantContext();
    }

    public function handle(): void
    {
        self::$runs++;
    }
}

beforeEach(function (): void {
    FakeTenantBusinessJob::$observedTenantId = null;
    FakeTenantBusinessJob::$runs = 0;
    FakeTenantMaintenanceJob::$runs = 0;

    $this->tenant = Tenant::factory()->create();
});

test('a job dispatched under a tenant runs with that tenant context', function (): void {
    withTenant($this->tenant, fn () => FakeTenantBusinessJob::dispatch());

    expect(FakeTenantBusinessJob::$observedTenantId)->toBe($this->tenant->id)
        ->and(FakeTenantBusinessJob::$runs)->toBe(1);
});

test('the job payload carries tenant_id as server-generated metadata', function (): void {
    Queue::fake();

    withTenant($this->tenant, fn () => FakeTenantBusinessJob::dispatch());

    Queue::assertPushed(
        FakeTenantBusinessJob::class,
        fn (FakeTenantBusinessJob $job): bool => $job->tenantId === $this->tenant->id,
    );
});

test('dispatching a tenant-aware job without context fails at dispatch time', function (): void {
    FakeTenantBusinessJob::dispatch();
})->throws(MissingTenantContextException::class);

test('context does not leak outside the job execution', function (): void {
    withTenant($this->tenant, fn () => FakeTenantBusinessJob::dispatch());

    expect(app(TenantContext::class)->has())->toBeFalse();
});

test('a business job for a suspended tenant is released for retry, not executed', function (): void {
    $job = withTenant($this->tenant, fn (): FakeTenantBusinessJob => new FakeTenantBusinessJob);

    $this->tenant->update(['status' => TenantStatus::Suspended]);

    $queueJob = Mockery::mock(QueueJobContract::class);
    $queueJob->shouldReceive('release')->once()->with(300);
    $job->setJob($queueJob);

    (new RestoreTenantContext)->handle($job, function (): void {
        throw new RuntimeException('handle() must not run for a suspended tenant');
    });

    expect(FakeTenantBusinessJob::$runs)->toBe(0);
});

test('a business job for a pending tenant is released for retry', function (): void {
    $job = withTenant($this->tenant, fn (): FakeTenantBusinessJob => new FakeTenantBusinessJob);

    $this->tenant->update(['status' => TenantStatus::Pending]);

    $queueJob = Mockery::mock(QueueJobContract::class);
    $queueJob->shouldReceive('release')->once()->with(300);
    $job->setJob($queueJob);

    (new RestoreTenantContext)->handle($job, fn () => throw new RuntimeException('must not run'));
});

test('a job for an archived tenant is cancelled, never processed', function (): void {
    $job = withTenant($this->tenant, fn (): FakeTenantBusinessJob => new FakeTenantBusinessJob);

    $this->tenant->update(['status' => TenantStatus::Suspended]);
    $this->tenant->update(['status' => TenantStatus::Archived]);

    $queueJob = Mockery::mock(QueueJobContract::class);
    $queueJob->shouldReceive('delete')->once();
    $job->setJob($queueJob);

    (new RestoreTenantContext)->handle($job, fn () => throw new RuntimeException('must not run'));

    expect(FakeTenantBusinessJob::$runs)->toBe(0);
});

test('a job whose tenant no longer exists fails loudly', function (): void {
    $job = withTenant($this->tenant, fn (): FakeTenantBusinessJob => new FakeTenantBusinessJob);

    $job->tenantId = 999999; // simulate a dangling reference

    $queueJob = Mockery::mock(QueueJobContract::class);
    $queueJob->shouldReceive('fail')->once()->with(Mockery::type(InvalidTenantContextException::class));
    $job->setJob($queueJob);

    (new RestoreTenantContext)->handle($job, fn () => throw new RuntimeException('must not run'));
});

test('a maintenance job still runs while the tenant is suspended', function (): void {
    $job = withTenant($this->tenant, fn (): FakeTenantMaintenanceJob => new FakeTenantMaintenanceJob);

    $this->tenant->update(['status' => TenantStatus::Suspended]);

    (new RestoreTenantContext)->handle($job, function (object $job): void {
        $job->handle();
    });

    expect(FakeTenantMaintenanceJob::$runs)->toBe(1);
});

test('the worker context is restored to empty after each job', function (): void {
    $job = withTenant($this->tenant, fn (): FakeTenantBusinessJob => new FakeTenantBusinessJob);

    (new RestoreTenantContext)->handle($job, function (object $job): void {
        expect(app(TenantContext::class)->id())->toBe($this->tenant->id);
        $job->handle();
    });

    expect(app(TenantContext::class)->has())->toBeFalse();
});
