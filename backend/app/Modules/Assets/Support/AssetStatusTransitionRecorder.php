<?php

namespace App\Modules\Assets\Support;

use App\Core\Shared\CorrelationId;
use App\Models\User;
use App\Modules\Assets\Enums\AssetStatus;
use App\Modules\Assets\Models\Asset;
use App\Modules\Assets\Models\AssetStatusTransition;

final class AssetStatusTransitionRecorder
{
    public function __construct(
        private readonly CorrelationId $correlationId,
    ) {}

    public function record(
        Asset $asset,
        ?AssetStatus $from,
        AssetStatus $to,
        User $actor,
        ?string $reason = null,
        ?int $custodyId = null,
    ): AssetStatusTransition {
        return AssetStatusTransition::query()->create([
            'asset_id' => $asset->id,
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'reason' => $reason,
            'performed_by' => $actor->id,
            'correlation_id' => $this->correlationId->get(),
            'custody_id' => $custodyId,
        ]);
    }
}
