<?php

namespace App\Core\Demo\Console;

use App\Core\Demo\MvpDemoDatasetBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Provisions an isolated Demo/UAT database with a realistic MVP dataset.
 *
 * Safety:
 * - Refuses APP_ENV=production
 * - Requires --force-demo (never use --force as production bypass)
 * - Never runs migrate:fresh / wipe
 * - Targets only the configured demo database name (default: erp_hajj_demo)
 * - Does not modify the development database configured in .env
 */
class SeedMvpDemoCommand extends Command
{
    protected $signature = 'demo:seed
                            {--force-demo : Explicit confirmation that this is a Demo/UAT seed}
                            {--database-name=erp_hajj_demo : Isolated MySQL/MariaDB database name}
                            {--rebuild : DROP and recreate the demo database only (never touches non-demo DBs)}
                            {--skip-migrate : Skip migrate on the demo database}
                            {--skip-scanners : Skip notification scanners after seeding}';

    protected $description = 'Create/migrate isolated Demo DB and seed realistic MVP presentation data (UAT only)';

    public function handle(MvpDemoDatasetBuilder $builder): int
    {
        if (app()->environment('production')) {
            $this->error('Refusing to run demo:seed in production.');

            return self::FAILURE;
        }

        if (! $this->option('force-demo')) {
            $this->error('Refusing to run without --force-demo. This command is Demo/UAT only.');

            return self::FAILURE;
        }

        $demoDb = (string) $this->option('database-name');
        if (! preg_match('/^[a-zA-Z0-9_]+$/', $demoDb) || ! str_contains(mb_strtolower($demoDb), 'demo')) {
            $this->error('Database name must be alphanumeric/underscore and contain "demo" (e.g. erp_hajj_demo).');

            return self::FAILURE;
        }

        $originalDb = (string) config('database.connections.mysql.database');
        if ($originalDb === $demoDb) {
            $this->warn("Current .env already points at {$demoDb}. Proceeding on that connection only.");
        } else {
            $this->info("Development DB ({$originalDb}) will not be modified.");
            $this->info("Switching this process to isolated database: {$demoDb}");
        }

        try {
            if ($this->option('rebuild')) {
                $this->warn("Rebuilding demo database {$demoDb} (DROP + CREATE)...");
                $this->dropDatabase($demoDb);
            }

            $this->ensureDatabaseExists($demoDb);
            $this->useDatabase($demoDb);

            if (! $this->option('skip-migrate')) {
                $this->info('Running migrations on demo database (migrate, not fresh)...');
                $code = Artisan::call('migrate', ['--force' => true]);
                $this->output->write(Artisan::output());
                if ($code !== 0) {
                    $this->error('Migrations failed on demo database.');

                    return self::FAILURE;
                }
            }

            $summary = $builder->seed($this);
            $this->printSummary($summary);

            if (! $this->option('skip-scanners')) {
                $this->info('Running notification scanners (twice for dedupe check)...');
                foreach (['contracts', 'tasks', 'custodies', 'low-stock', 'meetings-soon'] as $scan) {
                    Artisan::call('notifications:scan-'.$scan);
                    $this->line(trim(Artisan::output()) ?: "notifications:scan-{$scan} done");
                    Artisan::call('notifications:scan-'.$scan);
                }
                Artisan::call('contracts:expire');
                $this->line(trim(Artisan::output()) ?: 'contracts:expire done');
            }

            $this->newLine();
            $this->info('Demo/UAT dataset ready.');
            $this->warn("To use the UI against demo data, set DB_DATABASE={$demoDb} in backend/.env and restart php artisan serve.");
            $this->line('Demo password for all seeded accounts: Demo@123456');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Demo seed failed: '.$e->getMessage());
            $this->line($e->getFile().':'.$e->getLine());

            return self::FAILURE;
        } finally {
            if ($originalDb !== '' && $originalDb !== $demoDb) {
                $this->useDatabase($originalDb);
            }
        }
    }

    private function dropDatabase(string $database): void
    {
        if (! str_contains(mb_strtolower($database), 'demo')) {
            throw new \RuntimeException('Refusing to drop a database whose name does not contain "demo".');
        }

        $server = config('database.connections.mysql');
        $pdo = new \PDO(
            sprintf('mysql:host=%s;port=%s', $server['host'], $server['port'] ?? 3306),
            $server['username'],
            $server['password'] ?? '',
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION],
        );

        $pdo->exec(sprintf('DROP DATABASE IF EXISTS `%s`', str_replace('`', '``', $database)));
    }

    private function ensureDatabaseExists(string $database): void
    {
        $charset = (string) config('database.connections.mysql.charset', 'utf8mb4');
        $collation = (string) config('database.connections.mysql.collation', 'utf8mb4_unicode_ci');

        $server = config('database.connections.mysql');
        $pdo = new \PDO(
            sprintf('mysql:host=%s;port=%s', $server['host'], $server['port'] ?? 3306),
            $server['username'],
            $server['password'] ?? '',
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION],
        );

        $pdo->exec(sprintf(
            'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET %s COLLATE %s',
            str_replace('`', '``', $database),
            $charset,
            $collation,
        ));

        $this->info("Ensured database exists: {$database} ({$charset}/{$collation})");
    }

    private function useDatabase(string $database): void
    {
        config(['database.connections.mysql.database' => $database]);
        DB::purge('mysql');
        DB::reconnect('mysql');
        DB::setDefaultConnection('mysql');
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function printSummary(array $summary): void
    {
        $this->newLine();
        $this->info('=== Demo seed summary ===');
        foreach ($summary as $key => $value) {
            if (is_array($value)) {
                $this->line($key.': '.json_encode($value, JSON_UNESCAPED_UNICODE));
            } else {
                $this->line($key.': '.$value);
            }
        }
    }
}
