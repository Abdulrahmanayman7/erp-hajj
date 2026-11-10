<?php

namespace App\Console\Commands;

use App\Modules\ExecutiveAlerts\Actions\ScanExecutiveAlerts;
use Illuminate\Console\Command;

class ScanExecutiveAlertsCommand extends Command
{
    protected $signature = 'executive-alerts:scan';

    protected $description = 'Scan READY S10 executive alert rules (ALT-RISK-HIGH, ALT-PROC-OVERDUE) for all active tenants';

    public function handle(ScanExecutiveAlerts $action): int
    {
        $stats = $action->scanAllActiveTenants();
        $this->info(sprintf(
            'tenants=%d opened=%d refreshed=%d cleared=%d',
            $stats['tenants'],
            $stats['opened'],
            $stats['refreshed'],
            $stats['cleared'],
        ));

        return self::SUCCESS;
    }
}
