<?php

namespace App\Modules\Inventory\Actions;

use App\Core\Tenancy\TenantContext;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListWarehouses
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Warehouse>
     */
    public function execute(array $filters): LengthAwarePaginator
    {
        $this->tenantContext->require();

        $query = Warehouse::query()
            ->with(['organizationUnit', 'responsibleEmployee', 'createdBy']);

        $search = isset($filters['search']) ? trim((string) $filters['search']) : '';
        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('warehouse_number', 'like', '%'.$search.'%')
                    ->orWhere('location', 'like', '%'.$search.'%');
            });
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null && $filters['is_active'] !== '') {
            $active = $filters['is_active'];
            $query->where('is_active', $active === true || $active === 1 || $active === '1' || $active === 'true');
        }

        if (! empty($filters['organization_unit_id'])) {
            $query->where('organization_unit_id', (int) $filters['organization_unit_id']);
        }

        if (! empty($filters['responsible_employee_id'])) {
            $query->where('responsible_employee_id', (int) $filters['responsible_employee_id']);
        }

        $sort = $filters['sort'] ?? 'name';
        $allowed = ['name', 'warehouse_number', 'created_at', 'is_active'];
        if (! in_array($sort, $allowed, true)) {
            $sort = 'name';
        }
        $direction = strtolower((string) ($filters['direction'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sort, $direction)->orderBy('id');

        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return $query->paginate($perPage);
    }
}
