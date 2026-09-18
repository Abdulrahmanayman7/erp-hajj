<?php

namespace App\Modules\Contracts\Actions;

use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Contracts\Enums\ContractStatus;
use App\Modules\Contracts\Exceptions\ContractDomainException;
use App\Modules\Contracts\Models\Contract;
use App\Modules\Contracts\Support\ContractLifecycle;
use App\Modules\Contracts\Support\ContractTransitionRecorder;
use App\Modules\Notifications\Support\NotificationHooks;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Shared atomic lifecycle transition: lock → validate → update → history → audit.
 */
final class TransitionContract
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly ContractTransitionRecorder $transitions,
        private readonly AuthorizationSecurity $security,
        private readonly NotificationHooks $notifications,
    ) {}

    public function execute(
        ?User $actor,
        Contract $contract,
        ContractStatus $to,
        ?string $comment,
        string $auditEvent,
        ?Request $request = null,
        array $auditExtra = [],
    ): Contract {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $contract, $to, $comment, $auditEvent, $request, $auditExtra, $tenant): Contract {
            $locked = Contract::query()->whereKey($contract->id)->lockForUpdate()->firstOrFail();
            $from = $locked->status;

            ContractLifecycle::assertAllowed($from, $to);

            if (ContractLifecycle::requiresComment($from, $to) && ($comment === null || trim($comment) === '')) {
                throw ContractDomainException::commentRequired();
            }

            $locked->status = $to;
            $locked->save();

            $this->transitions->record($locked, $from, $to, $actor, $comment !== null ? trim($comment) : null, $request);

            $this->security->record($auditEvent, array_merge([
                'tenant_id' => $tenant->id,
                'actor_id' => $actor?->id,
                'contract_id' => $locked->id,
                'contract_number' => $locked->contract_number,
                'from_status' => $from->value,
                'to_status' => $to->value,
            ], $auditExtra), $request);

            if ($to === ContractStatus::Expired) {
                $this->notifications->contractExpired($locked);
            }

            return $locked->load([
                'category',
                'employee',
                'organizationUnit',
                'createdBy',
                'renewedFrom',
                'renewalChild',
                'statusTransitions.actor',
            ]);
        });
    }
}
