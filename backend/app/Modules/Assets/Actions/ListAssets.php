<?php

namespace App\Modules\Assets\Actions;

use App\Core\Tenancy\TenantContext;
use App\Modules\Assets\Enums\AssetStatus;
use App\Modules\Assets\Enums\CustodyStatus;
use App\Modules\Assets\Models\Asset;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListAssets
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Asset>
     */
    public function execute(array $filters): LengthAwarePaginator
    {
        $this->tenantContext->require();

        $query = Asset::query()->with([
            'category',
            'warehouse',
            'organizationUnit',
            'currentCustody.employee',
            'createdBy',
        ]);

        $search = isset($filters['search']) ? trim((string) $filters['search']) : '';
        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('asset_number', 'like', '%'.$search.'%')
                    ->orWhere('serial_number', 'like', '%'.$search.'%')
                    ->orWhere('barcode', 'like', '%'.$search.'%');
            });
        }

        if (! empty($filters['status']) && in_array((string) $filters['status'], AssetStatus::values(), true)) {
            $query->where('status', (string) $filters['status']);
        }

        if (! empty($filters['category_id'])) {
            $query->where('category_id', (int) $filters['category_id']);
        }

        if (! empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', (int) $filters['warehouse_id']);
        }

        if (! empty($filters['organization_unit_id'])) {
            $query->where('organization_unit_id', (int) $filters['organization_unit_id']);
        }

        if (! empty($filters['employee_id'])) {
            $employeeId = (int) $filters['employee_id'];
            $query->whereHas('currentCustody', function ($q) use ($employeeId): void {
                $q->where('employee_id', $employeeId)
                    ->where('status', CustodyStatus::Active->value);
            });
        }

        if (isset($filters['serial_number']) && trim((string) $filters['serial_number']) !== '') {
            $query->where('serial_number', trim((string) $filters['serial_number']));
        }

        if (! empty($filters['acquisition_from'])) {
            $query->whereDate('acquisition_date', '>=', (string) $filters['acquisition_from']);
        }

        if (! empty($filters['acquisition_to'])) {
            $query->whereDate('acquisition_date', '<=', (string) $filters['acquisition_to']);
        }

        $query->orderBy('asset_number')->orderBy('id');

        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return $query->paginate($perPage);
    }
}
