<?php

namespace App\Modules\ExecutiveAlerts\Support;

use App\Modules\RisksAndComplaints\Enums\ComplaintStatus;
use App\Modules\RisksAndComplaints\Models\OperationalComplaint;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * U03 fixed 48 calendar-hour complaint SLA for S10 alerts (prospective activation).
 */
final class ComplaintSlaEvaluator
{
    public function hours(): int
    {
        return max(1, (int) config('executive_alerts.complaint_sla_hours', 48));
    }

    public function activationAt(): ?Carbon
    {
        $raw = config('executive_alerts.complaint_sla_activated_at');
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        try {
            return Carbon::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    public function isPolicyActive(): bool
    {
        return $this->activationAt() !== null;
    }

    public function clockStart(OperationalComplaint $complaint): ?Carbon
    {
        if ($complaint->reopened_at !== null) {
            return $complaint->reopened_at->copy();
        }

        return $complaint->created_at?->copy();
    }

    public function isOpenStatus(ComplaintStatus|string $status): bool
    {
        $value = $status instanceof ComplaintStatus ? $status->value : $status;

        return in_array($value, [
            ComplaintStatus::New->value,
            ComplaintStatus::UnderReview->value,
            ComplaintStatus::InProgress->value,
        ], true);
    }

    /**
     * @return array{eligible: bool, breached: bool, reason: string|null, due_at: string|null}
     */
    public function evaluate(OperationalComplaint $complaint, CarbonInterface $now): array
    {
        $activation = $this->activationAt();
        if ($activation === null) {
            return ['eligible' => false, 'breached' => false, 'reason' => 'policy_disabled', 'due_at' => null];
        }

        $clockStart = $this->clockStart($complaint);
        if ($clockStart === null) {
            return ['eligible' => false, 'breached' => false, 'reason' => 'missing_clock_start', 'due_at' => null];
        }

        if ($clockStart->lt($activation)) {
            return ['eligible' => false, 'breached' => false, 'reason' => 'historical_excluded', 'due_at' => null];
        }

        $dueAt = $clockStart->copy()->addHours($this->hours());

        if (! $this->isOpenStatus($complaint->status)) {
            // Resolved stops the clock; admin close without resolve is not SLA-met but not an open breach.
            return [
                'eligible' => true,
                'breached' => false,
                'reason' => $complaint->status === ComplaintStatus::Resolved
                    ? 'resolved'
                    : 'not_open',
                'due_at' => $dueAt->toIso8601String(),
            ];
        }

        $breached = $now->greaterThanOrEqualTo($dueAt);

        return [
            'eligible' => true,
            'breached' => $breached,
            'reason' => $breached ? 'breached' : 'within_sla',
            'due_at' => $dueAt->toIso8601String(),
        ];
    }
}
