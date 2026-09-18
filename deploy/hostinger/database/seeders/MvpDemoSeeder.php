<?php

namespace Database\Seeders;

use App\Core\Demo\MvpDemoDatasetBuilder;
use Illuminate\Database\Seeder;

/**
 * Seeds realistic MVP Demo/UAT data onto the *current* database connection.
 *
 * Prefer the guarded Artisan command:
 *   php artisan demo:seed --force-demo
 *
 * Do not run this against production. Prefer a dedicated database such as erp_hajj_demo.
 */
class MvpDemoSeeder extends Seeder
{
    public function run(MvpDemoDatasetBuilder $builder): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException('MvpDemoSeeder refuses to run in production.');
        }

        $database = (string) config('database.connections.'.config('database.default').'.database');
        if ($database !== '' && ! str_contains(mb_strtolower($database), 'demo')) {
            throw new \RuntimeException(
                "Refusing MvpDemoSeeder on non-demo database [{$database}]. Use: php artisan demo:seed --force-demo",
            );
        }

        $summary = $builder->seed($this->command);
        foreach ($summary as $key => $value) {
            $this->command?->line($key.': '.(is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value));
        }
    }
}
