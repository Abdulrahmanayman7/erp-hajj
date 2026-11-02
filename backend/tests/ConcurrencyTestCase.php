<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Concurrency suites spawn separate PHP processes that must see committed
 * fixture rows. RefreshDatabase's default per-test transaction hides those
 * rows from workers, so this base case migrates fresh without wrapping.
 *
 * Destructive migrate:fresh is refused unless the connection is disposable
 * sqlite (:memory: / :memory: variants) or MySQL/MariaDB erp_hajj_testing.
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
        $this->assertDisposableConcurrencyDatabase();

        $this->artisan('migrate:fresh', $this->migrateFreshUsing());

        $this->app[Kernel::class]->setArtisan(null);

        RefreshDatabaseState::$migrated = true;
    }

    private function assertDisposableConcurrencyDatabase(): void
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $database = (string) $connection->selectOne('select database() as db')->db;
            if ($database !== 'erp_hajj_testing') {
                throw new RuntimeException(
                    "ConcurrencyTestCase refused migrate:fresh on MySQL database [{$database}]; expected erp_hajj_testing."
                );
            }

            return;
        }

        if ($driver === 'sqlite') {
            $database = (string) $connection->getDatabaseName();
            if ($database !== ':memory:' && ! str_contains($database, 'mode=memory')) {
                throw new RuntimeException(
                    "ConcurrencyTestCase refused migrate:fresh on SQLite database [{$database}]; expected :memory:."
                );
            }

            return;
        }

        throw new RuntimeException(
            "ConcurrencyTestCase refused migrate:fresh on unsupported driver [{$driver}]."
        );
    }
}
