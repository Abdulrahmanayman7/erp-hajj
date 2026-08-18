<?php

namespace App\Modules\Contracts\Actions;

use App\Core\Tenancy\TenantContext;
use App\Modules\Contracts\Enums\ContractStatus;
use App\Modules\Contracts\Models\Contract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

final class ListContracts
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * @param  array{
     *     search?: string|null,
     *     status?: string|null,
     *     category_id?: int|string|null,
     *     employee_id?: int|string|null,
     *     organization_unit_id?: int|string|null,
     *     expiring_soon?: int|string|bool|null,
     *     start_date_from?: string|null,
     *     start_date_to?: string|null,
     *     end_date_from?: string|null,
     *     end_date_to?: string|null,
     *     sort?: string|null,
     *     direction?: string|null,
     *     per_page?: int|string|null,
     *     page?: int|string|null
     * }  $filters
     * @return LengthAwarePaginator<int, Contract>
     */
    public function execute(array $filters): LengthAwarePaginator
    {
        $tenant = $this->tenantContext->require();
        $timezone = $tenant->timezone ?: config('app.timezone', 'Asia/Riyadh');
        $today = now($timezone)->toDateString();

        $query = Contract::query()
            ->with(['category', 'employee', 'organizationUnit', 'createdBy']);

        $search = isset($filters['search']) ? trim((string) $filters['search']) : '';
        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('title', 'like', '%'.$search.'%')
                    ->orWhere('contract_number', 'like', '%'.$search.'%')
                    ->orWhere('counterparty_name', 'like', '%'.$search.'%');
            });
        }

        $status = isset($filters['status']) ? trim((string) $filters['status']) : '';
        if ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        if (! empty($filters['category_id'])) {
            $query->where('contract_category_id', (int) $filters['category_id']);
        }

        if (! empty($filters['employee_id'])) {
            $query->where('employee_id', (int) $filters['employee_id']);
        }

        if (! empty($filters['organization_unit_id'])) {
            $query->where('organization_unit_id', (int) $filters['organization_unit_id']);
        }

        $expiringSoon = $filters['expiring_soon'] ?? null;
        if ($expiringSoon === 1 || $expiringSoon === '1' || $expiringSoon === true || $expiringSoon === 'true') {
            $days = max(0, (int) config('contracts.expiring_soon_days', 30));
            $until = Carbon::parse($today, $timezone)->addDays($days)->toDateString();

            $query->where('status', ContractStatus::Executing->value)
                ->whereNotNull('end_date')
                ->where('end_date', '>=', $today)
                ->where('end_date', '<=', $until);
        }

        if (! empty($filters['start_date_from'])) {
            $query->where('start_date', '>=', (string) $filters['start_date_from']);
        }
        if (! empty($filters['start_date_to'])) {
            $query->where('start_date', '<=', (string) $filters['start_date_to']);
        }
        if (! empty($filters['end_date_from'])) {
            $query->where('end_date', '>=', (string) $filters['end_date_from']);
        }
        if (! empty($filters['end_date_to'])) {
            $query->where('end_date', '<=', (string) $filters['end_date_to']);
        }

        $sort = $filters['sort'] ?? 'created_at';
        $allowedSorts = ['created_at', 'start_date', 'end_date', 'title', 'contract_number', 'status'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'created_at';
        }

        $direction = strtolower((string) ($filters['direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sort, $direction)->orderBy('id', 'desc');

        $perPage = (int) ($filters['per_page'] ?? 15);
        $perPage = max(1, min($perPage, 100));

        return $query->paginate($perPage);
    }
}
