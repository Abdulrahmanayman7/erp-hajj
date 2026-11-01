<?php

namespace App\Modules\SuppliersAndProcurement\Resources;

use App\Modules\SuppliersAndProcurement\Enums\PurchaseDemandGroupStatus;
use App\Modules\SuppliersAndProcurement\Models\PurchaseDemandGroup;
use App\Modules\SuppliersAndProcurement\Models\PurchaseDemandGroupActiveMembership;
use App\Modules\SuppliersAndProcurement\Models\PurchaseDemandGroupItem;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PurchaseDemandGroup */
class PurchaseDemandGroupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->status instanceof PurchaseDemandGroupStatus
            ? $this->status
            : PurchaseDemandGroupStatus::from((string) $this->status);

        $payload = [
            'id' => $this->id,
            'group_number' => $this->group_number,
            'title' => $this->title,
            'notes' => $this->notes,
            'status' => $status->value,
            'season_id' => $this->season_id,
            'beneficiary_organization_unit_id' => $this->beneficiary_organization_unit_id,
            'created_by' => $this->created_by,
            'frozen_at' => $this->frozen_at?->toIso8601String(),
            'frozen_by' => $this->frozen_by,
            'freeze_reason' => $this->freeze_reason,
            'closed_at' => $this->closed_at?->toIso8601String(),
            'closed_by' => $this->closed_by,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'hidden_member_count' => 0,
        ];

        if ($this->relationLoaded('activeMemberships')) {
            $members = [];
            $pricedTotal = '0.00';
            $unpricedLineCount = 0;
            $pricedKnown = false;

            foreach ($this->activeMemberships as $membership) {
                /** @var PurchaseDemandGroupActiveMembership $membership */
                $pr = $membership->relationLoaded('purchaseRequest')
                    ? $membership->purchaseRequest
                    : null;

                $row = [
                    'purchase_request_id' => $membership->purchase_request_id,
                    'item_id' => $membership->item_id,
                ];

                if ($pr instanceof PurchaseRequest) {
                    $row['pr_number'] = $pr->pr_number;
                    $row['title'] = $pr->title;
                    $row['status'] = $pr->status->value;
                    $row['version_no'] = $pr->version_no;
                    $row['season_id'] = $pr->season_id;
                    $row['beneficiary_organization_unit_id'] = $pr->beneficiary_organization_unit_id;
                    $row['estimated_total_priced'] = (string) $pr->estimated_total_priced;
                    $row['has_unpriced_lines'] = (bool) $pr->has_unpriced_lines;

                    if ($pr->estimated_total_priced !== null) {
                        $pricedTotal = bcadd($pricedTotal, (string) $pr->estimated_total_priced, 2);
                        $pricedKnown = true;
                    }
                    if ($pr->has_unpriced_lines) {
                        $unpricedLineCount++;
                    }
                }

                $members[] = $row;
            }

            $payload['active_members'] = $members;
            $payload['active_member_count'] = count($members);
            $payload['planning_priced_total'] = $pricedKnown ? $pricedTotal : null;
            $payload['unpriced_member_count'] = $unpricedLineCount;
        }

        if ($this->relationLoaded('items')) {
            $payload['items'] = $this->items->map(
                function (PurchaseDemandGroupItem $item): array {
                    return [
                        'id' => $item->id,
                        'purchase_request_id' => $item->purchase_request_id,
                        'pr_version_no_at_link' => $item->pr_version_no_at_link,
                        'linked_at' => $item->linked_at?->toIso8601String(),
                        'linked_by' => $item->linked_by,
                        'unlinked_at' => $item->unlinked_at?->toIso8601String(),
                        'unlinked_by' => $item->unlinked_by,
                        'unlink_reason' => $item->unlink_reason,
                    ];
                },
            )->all();
        }

        return $payload;
    }
}
