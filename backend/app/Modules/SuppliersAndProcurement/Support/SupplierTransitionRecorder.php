<?php

namespace App\Modules\SuppliersAndProcurement\Support;

use App\Core\Shared\CorrelationId;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Enums\SupplierStatus;
use App\Modules\SuppliersAndProcurement\Models\Supplier;
use App\Modules\SuppliersAndProcurement\Models\SupplierStatusTransition;
use Illuminate\Http\Request;

final class SupplierTransitionRecorder
{
    public function __construct(
        private readonly CorrelationId $correlationId,
    ) {}

    public function record(
        Supplier $supplier,
        ?SupplierStatus $from,
        SupplierStatus $to,
        ?User $actor,
        ?string $reason = null,
        ?Request $request = null,
    ): SupplierStatusTransition {
        $transition = new SupplierStatusTransition([
            'supplier_id' => $supplier->id,
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'actor_user_id' => $actor?->id,
            'reason' => $reason,
            'correlation_id' => $request !== null ? $this->correlationId->get() : null,
            'created_at' => now(),
        ]);
        $transition->save();

        return $transition;
    }
}
