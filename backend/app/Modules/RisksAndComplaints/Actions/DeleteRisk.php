<?php

namespace App\Modules\RisksAndComplaints\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\RisksAndComplaints\Exceptions\RisksAndComplaintsDomainException;
use App\Modules\RisksAndComplaints\Models\OperationalRisk;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class DeleteRisk
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, OperationalRisk $risk, Request $request): void
    {
        $tenant = $this->tenantContext->require();

        DB::transaction(function () use ($actor, $risk, $request, $tenant): void {
            $locked = OperationalRisk::query()->whereKey($risk->id)->lockForUpdate()->firstOrFail();

            if (! $locked->status->allowsSoftDelete()) {
                throw RisksAndComplaintsDomainException::riskDeleteNotAllowed();
            }

            $payload = [
                'tenant_id' => $tenant->id,
                'season_id' => $locked->season_id,
                'actor_id' => $actor->id,
                'risk_id' => $locked->id,
                'risk_number' => $locked->risk_number,
                'before' => [
                    'status' => $locked->status->value,
                    'title' => $locked->title,
                ],
            ];

            $locked->delete();

            $this->security->record(AuthorizationSecurityEvent::RISK_DELETED, $payload, $request);
        });
    }
}
