<?php

namespace App\Modules\SuppliersAndProcurement\Resources;

use App\Modules\SuppliersAndProcurement\Models\SupplierBankAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Always-masked banking projection (IIB-D12). Never includes plaintext/ciphertext/fingerprint.
 *
 * @mixin SupplierBankAccount
 */
class SupplierBankAccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var SupplierBankAccount $account */
        $account = $this->resource;

        return $account->toMaskedArray() + [
            'supplier_id' => $account->supplier_id,
            'label' => $account->label,
            'rejected_at' => $account->rejected_at?->toIso8601String(),
            'revoked_at' => $account->revoked_at?->toIso8601String(),
            'created_at' => $account->created_at?->toIso8601String(),
            'updated_at' => $account->updated_at?->toIso8601String(),
            'last_edited_by' => $account->last_edited_by,
            'rejected_by' => $account->rejected_by,
            'revoked_by' => $account->revoked_by,
        ];
    }
}
