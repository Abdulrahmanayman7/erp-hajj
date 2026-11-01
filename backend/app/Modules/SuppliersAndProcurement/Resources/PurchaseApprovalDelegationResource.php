<?php

namespace App\Modules\SuppliersAndProcurement\Resources;

use App\Modules\SuppliersAndProcurement\Models\PurchaseApprovalDelegation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PurchaseApprovalDelegation */
class PurchaseApprovalDelegationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'from_user_id' => $this->from_user_id,
            'from_user' => $this->userRef('fromUser', $this->from_user_id),
            'to_user_id' => $this->to_user_id,
            'to_user' => $this->userRef('toUser', $this->to_user_id),
            'max_amount' => (string) $this->max_amount,
            'currency' => $this->currency,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'authorized_by' => $this->userRef('authorizer', $this->authorized_by),
            'revoked_at' => $this->revoked_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function userRef(string $relation, mixed $id): ?array
    {
        if ($id === null) {
            return null;
        }

        $user = $this->relationLoaded($relation) ? $this->{$relation} : null;

        return ['id' => (int) $id, 'name' => $user?->name ?? ''];
    }
}
