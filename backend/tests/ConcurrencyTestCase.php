<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;

/**
 * Concurrency suites spawn separate PHP processes that must see committed
 * fixture rows. RefreshDatabase's default per-test transaction hides those
 * rows from workers, so this base case migrates fresh without wrapping.
 */
abstract class ConcurrencyTestCase extends TestCase
{
    use RefreshDatabase;

    /**
     * @var list<string>
     */
    protected $connectionsToTransact = [];

    protected function refreshTestDatabase(): void
    {
        $this->artisan('migrate:fresh', $this->migrateFreshUsing());

        $this->app[Kernel::class]->setArtisan(null);

        RefreshDatabaseState::$migrated = true;
    }
}
