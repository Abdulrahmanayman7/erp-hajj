<?php

namespace App\Modules\SuppliersAndProcurement\Policies;

use App\Models\User;
use App\Modules\SuppliersAndProcurement\Models\GoodsReceipt;

class GoodsReceiptPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('goods_receipts.view');
    }

    public function view(User $actor, GoodsReceipt $grn): bool
    {
        return $actor->hasPermission('goods_receipts.view')
            && $this->sameTenant($actor, $grn);
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('goods_receipts.create');
    }

    public function post(User $actor, GoodsReceipt $grn): bool
    {
        return $actor->hasPermission('goods_receipts.post')
            && $this->sameTenant($actor, $grn);
    }

    private function sameTenant(User $actor, GoodsReceipt $grn): bool
    {
        return $actor->tenant_id !== null
            && (int) $actor->tenant_id === (int) $grn->tenant_id;
    }
}
