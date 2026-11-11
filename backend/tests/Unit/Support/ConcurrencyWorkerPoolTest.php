<?php

use Symfony\Component\Process\Process;
use Tests\Support\ConcurrencyWorkerPool;

test('ConcurrencyWorkerPool stops still-running workers in finally', function (): void {
    $process = new Process([PHP_BINARY, '-r', 'usleep(500000); echo "done";']);
    $process->start();

    expect($process->isRunning())->toBeTrue();

    ConcurrencyWorkerPool::stopRunning([$process]);

    expect($process->isRunning())->toBeFalse();
});
