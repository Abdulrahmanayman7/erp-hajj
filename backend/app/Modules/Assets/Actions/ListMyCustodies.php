<?php

namespace App\Modules\Assets\Actions;

use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Assets\Models\AssetCustody;
use App\Modules\Assets\Support\AssetReferenceValidator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListMyCustodies
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AssetReferenceValidator $references,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, AssetCustody>
     */
    public function execute(User $actor, array $filters): LengthAwarePaginator
    {
        $this->tenantContext->require();

        $employee = $this->references->linkedEmployeeFor($actor);
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        if ($employee === null) {
            return AssetCustody::query()->whereRaw('1 = 0')->paginate($perPage);
        }

        $query = AssetCustody::query()
            ->with(['asset', 'employee', 'assignedBy', 'returnedBy'])
            ->where('employee_id', $employee->id)
            ->orderByDesc('assigned_at')
            ->orderByDesc('id');

        return $query->paginate($perPage);
    }
}
