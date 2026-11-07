<?php

namespace App\Modules\RisksAndComplaints\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\RisksAndComplaints\Exceptions\RisksAndComplaintsDomainException;
use App\Modules\RisksAndComplaints\Models\OperationalComplaint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class DeleteComplaint
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, OperationalComplaint $complaint, Request $request): void
    {
        $tenant = $this->tenantContext->require();

        DB::transaction(function () use ($actor, $complaint, $request, $tenant): void {
            $locked = OperationalComplaint::query()->whereKey($complaint->id)->lockForUpdate()->firstOrFail();

            if (! $locked->status->allowsSoftDelete() || $locked->assignee_employee_id !== null) {
                throw RisksAndComplaintsDomainException::complaintDeleteNotAllowed();
            }

            $payload = [
                'tenant_id' => $tenant->id,
                'season_id' => $locked->season_id,
                'actor_id' => $actor->id,
                'complaint_id' => $locked->id,
                'complaint_number' => $locked->complaint_number,
                'before' => [
                    'status' => $locked->status->value,
                    'title' => $locked->title,
                ],
            ];

            $locked->delete();

            $this->security->record(AuthorizationSecurityEvent::COMPLAINT_DELETED, $payload, $request);
        });
    }
}
