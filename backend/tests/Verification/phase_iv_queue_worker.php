<?php

/**
 * Gated independent queue worker for Phase IV QV verification.
 *
 * Asserts erp_hajj_testing before processing a single database-queue job.
 *
 *   php tests/Verification/phase_iv_queue_worker.php [label]
 */
declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

putenv('APP_ENV=testing');
putenv('DB_CONNECTION=mysql');
putenv('DB_DATABASE=erp_hajj_testing');
putenv('DB_URL=');
putenv('QUEUE_CONNECTION=database');
$_ENV['APP_ENV'] = 'testing';
$_ENV['DB_CONNECTION'] = 'mysql';
$_ENV['DB_DATABASE'] = 'erp_hajj_testing';
$_ENV['DB_URL'] = '';
$_ENV['QUEUE_CONNECTION'] = 'database';
$_SERVER['APP_ENV'] = 'testing';
$_SERVER['DB_CONNECTION'] = 'mysql';
$_SERVER['DB_DATABASE'] = 'erp_hajj_testing';
$_SERVER['QUEUE_CONNECTION'] = 'database';

require dirname(__DIR__, 2).'/vendor/autoload.php';
require __DIR__.'/phase_iv_db_gate.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$label = (string) ($argv[1] ?? 'queue-once');
$gate = phaseIvAssertTestingDb('queue-worker-'.$label.'-pid-'.getmypid());

if ($gate['queue'] !== 'database') {
    fwrite(STDERR, "REFUSE: QUEUE_CONNECTION must be database, got {$gate['queue']}\n");
    exit(5);
}

$exit = Artisan::call('queue:work', [
    'connection' => 'database',
    '--once' => true,
    '--stop-when-empty' => true,
    '--tries' => 5,
    '--timeout' => 60,
]);

echo json_encode([
    'pid' => getmypid(),
    'label' => $label,
    'db' => $gate,
    'artisan_exit' => $exit,
    'output' => Artisan::output(),
], JSON_UNESCAPED_UNICODE).PHP_EOL;

exit($exit === 0 ? 0 : 1);
