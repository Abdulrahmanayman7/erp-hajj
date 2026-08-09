<?php

namespace App\Modules\Contracts\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Models\User;
use App\Modules\Contracts\Enums\ContractStatus;
use App\Modules\Contracts\Models\Contract;
use Illuminate\Http\Request;

final class ReturnContractToDraft
{
    public function __construct(
        private readonly TransitionContract $transition,
    ) {}

    public function execute(User $actor, Contract $contract, ?string $comment, Request $request): Contract
    {
        return $this->transition->execute(
            $actor,
            $contract,
            ContractStatus::Draft,
            $comment,
            AuthorizationSecurityEvent::CONTRACT_RETURNED_DRAFT,
            $request,
        );
    }
}
