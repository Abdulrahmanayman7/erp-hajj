<?php

namespace App\Modules\Contracts\Support;

use App\Core\Shared\CorrelationId;
use App\Models\User;
use App\Modules\Contracts\Enums\ContractStatus;
use App\Modules\Contracts\Models\Contract;
use App\Modules\Contracts\Models\ContractStatusTransition;
use Illuminate\Http\Request;

final class ContractTransitionRecorder
{
    public function __construct(
        private readonly CorrelationId $correlationId,
    ) {}

    public function record(
        Contract $contract,
        ?ContractStatus $from,
        ContractStatus $to,
        ?User $actor,
        ?string $comment = null,
        ?Request $request = null,
    ): ContractStatusTransition {
        $transition = new ContractStatusTransition([
            'contract_id' => $contract->id,
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'actor_user_id' => $actor?->id,
            'comment' => $comment,
            'correlation_id' => $request !== null ? $this->correlationId->get() : null,
            'created_at' => now(),
        ]);
        $transition->save();

        return $transition;
    }
}
