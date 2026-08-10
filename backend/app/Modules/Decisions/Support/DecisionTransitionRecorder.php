<?php

namespace App\Modules\Decisions\Support;

use App\Core\Shared\CorrelationId;
use App\Models\User;
use App\Modules\Decisions\Enums\DecisionStatus;
use App\Modules\Decisions\Models\Decision;
use App\Modules\Decisions\Models\DecisionStatusTransition;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class DecisionTransitionRecorder
{
    public function __construct(
        private readonly CorrelationId $correlationId,
    ) {}

    public function record(
        Decision $decision,
        ?DecisionStatus $from,
        DecisionStatus $to,
        ?User $actor,
        ?string $comment = null,
        ?Request $request = null,
    ): DecisionStatusTransition {
        $correlation = $this->correlationId->get();
        if ($correlation === null || $correlation === '') {
            $correlation = (string) Str::uuid();
        }

        $transition = new DecisionStatusTransition([
            'decision_id' => $decision->id,
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'performed_by' => $actor?->id,
            'comment' => $comment,
            'correlation_id' => $correlation,
            'created_at' => now(),
        ]);
        $transition->save();

        return $transition;
    }
}
