<?php

namespace App\Modules\Assets\Actions;

use App\Core\Tenancy\TenantContext;
use App\Modules\Assets\Enums\CustodyStatus;
use App\Modules\Assets\Models\AssetCustody;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListCustodies
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, AssetCustody>
     */
    public function execute(array $filters): LengthAwarePaginator
    {
        $this->tenantContext->require();

        $query = AssetCustody::query()->with([
            'asset',
            'employee',
            'assignedBy',
            'returnedBy',
        ]);

        if (! empty($filters['asset_id'])) {
            $query->where('asset_id', (int) $filters['asset_id']);
        }

        if (! empty($filters['employee_id'])) {
            $query->where('employee_id', (int) $filters['employee_id']);
        }

        if (! empty($filters['status']) && in_array((string) $filters['status'], CustodyStatus::values(), true)) {
            $query->where('status', (string) $filters['status']);
        }

        if (! empty($filters['assigned_from'])) {
            $query->whereDate('assigned_at', '>=', (string) $filters['assigned_from']);
        }

        if (! empty($filters['assigned_to'])) {
            $query->whereDate('assigned_at', '<=', (string) $filters['assigned_to']);
        }

        $overdue = $filters['overdue'] ?? null;
        if ($overdue === 1 || $overdue === '1' || $overdue === true || $overdue === 'true') {
            $query->where('status', CustodyStatus::Active->value)
                ->whereNotNull('expected_return_at')
                ->where('expected_return_at', '<', now());
        }

        $query->orderByDesc('assigned_at')->orderByDesc('id');

        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return $query->paginate($perPage);
    }
}
