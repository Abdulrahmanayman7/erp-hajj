<?php

namespace Tests\Support;

use Symfony\Component\Process\Process;

/**
 * Bounded await + cleanup for multiprocess concurrency workers.
 *
 * If a PHPUnit assertion fails mid-suite or wait() throws, leftover workers
 * must not keep holding MySQL locks on the shared erp_hajj_testing database.
 */
final class ConcurrencyWorkerPool
{
    /**
     * @param  list<Process>  $processes
     * @return list<Process>
     */
    public static function awaitAll(array $processes): array
    {
        try {
            foreach ($processes as $process) {
                $process->wait();
            }
        } finally {
            self::stopRunning($processes);
        }

        return $processes;
    }

    /**
     * @param  list<Process>  $processes
     */
    public static function stopRunning(array $processes): void
    {
        foreach ($processes as $process) {
            if ($process->isRunning()) {
                // Grace period then SIGKILL — CI MySQL job must not hang on orphans.
                $process->stop(1);
            }
        }
    }
}
