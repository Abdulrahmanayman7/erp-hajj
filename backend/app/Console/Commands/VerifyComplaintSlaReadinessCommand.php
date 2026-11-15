<?php

namespace App\Console\Commands;

use App\Modules\ExecutiveAlerts\Support\ComplaintSlaEvaluator;
use Illuminate\Console\Command;

/**
 * Non-production readiness check for U03 complaint SLA.
 *
 * Never activates production policy. Refuses APP_ENV=production.
 */
final class VerifyComplaintSlaReadinessCommand extends Command
{
    protected $signature = 'executive-alerts:verify-complaint-sla-readiness
                            {--smoke-activate : Temporarily set a disposable activation timestamp in-process only (never writes .env)}';

    protected $description = 'Verify U03 complaint SLA code paths without authorizing production activation';

    public function handle(ComplaintSlaEvaluator $evaluator): int
    {
        $env = (string) config('app.env');
        if ($env === 'production') {
            $this->error('REFUSED: complaint SLA readiness verification is forbidden in production.');

            return self::FAILURE;
        }

        $configured = config('executive_alerts.complaint_sla_activated_at');
        $this->line('APP_ENV='.$env);
        $this->line('EXECUTIVE_COMPLAINT_SLA_ACTIVATED_AT='.(is_string($configured) && $configured !== '' ? '[set]' : '[unset]'));
        $this->line('complaint_sla_hours='.(string) config('executive_alerts.complaint_sla_hours', 48));
        $this->line('policy_active_default='.($evaluator->isPolicyActive() ? 'true' : 'false'));

        if ($this->option('smoke-activate')) {
            $ts = now()->toIso8601String();
            config(['executive_alerts.complaint_sla_activated_at' => $ts]);
            $this->warn("In-process smoke activation only: {$ts}");
            $this->line('policy_active_after_smoke='.(app(ComplaintSlaEvaluator::class)->isPolicyActive() ? 'true' : 'false'));
            $this->warn('No .env write. Production activation remains BLOCKED without Owner authorization.');
        } else {
            $this->comment('Pass --smoke-activate for disposable in-process activation smoke (local/testing only).');
        }

        $this->info('U03 readiness check complete. Production activation NOT authorized by this command.');

        return self::SUCCESS;
    }
}
