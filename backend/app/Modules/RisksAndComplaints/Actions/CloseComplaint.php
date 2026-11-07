<?php

namespace App\Modules\RisksAndComplaints\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\RisksAndComplaints\Enums\ComplaintStatus;
use App\Modules\RisksAndComplaints\Exceptions\RisksAndComplaintsDomainException;
use App\Modules\RisksAndComplaints\Models\OperationalComplaint;
use App\Modules\RisksAndComplaints\Support\ComplaintLifecycle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CloseComplaint
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array{close_reason: string}  $data
     */
    public function execute(User $actor, OperationalComplaint $complaint, array $data, Request $request): OperationalComplaint
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $complaint, $data, $request, $tenant): OperationalComplaint {
            $locked = OperationalComplaint::query()->whereKey($complaint->id)->lockForUpdate()->firstOrFail();
            ComplaintLifecycle::assertCanClose($locked->status);

            $reason = trim((string) ($data['close_reason'] ?? ''));
            if ($reason === '') {
                throw RisksAndComplaintsDomainException::complaintCloseReasonRequired();
            }

            $before = [
                'status' => $locked->status->value,
                'close_reason' => $locked->close_reason,
            ];

            $locked->status = ComplaintStatus::Closed;
            $locked->close_reason = $reason;
            $locked->closed_by = $actor->id;
            $locked->closed_at = now();
            $locked->save();

            $this->security->record(AuthorizationSecurityEvent::COMPLAINT_CLOSED, [
                'tenant_id' => $tenant->id,
                'season_id' => $locked->season_id,
                'actor_id' => $actor->id,
                'complaint_id' => $locked->id,
                'complaint_number' => $locked->complaint_number,
                'before' => $before,
                'after' => [
                    'status' => $locked->status->value,
                    'close_reason' => $locked->close_reason,
                    'closed_by' => $locked->closed_by,
                    'closed_at' => $locked->closed_at?->toIso8601String(),
                ],
            ], $request);

            return $locked->load(['assigneeEmployee', 'createdBy', 'escalatedToEmployee', 'notes.createdBy']);
        });
    }
}
