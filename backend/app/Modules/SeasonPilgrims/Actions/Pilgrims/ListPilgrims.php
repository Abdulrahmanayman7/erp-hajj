<?php

namespace App\Modules\SeasonPilgrims\Actions\Pilgrims;

use App\Core\Tenancy\TenantContext;
use App\Modules\SeasonPilgrims\Models\Pilgrim;
use App\Modules\SeasonPilgrims\Models\PilgrimIdentityDocument;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListPilgrims
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Pilgrim>
     */
    public function execute(array $filters): LengthAwarePaginator
    {
        $this->tenantContext->require();

        $query = Pilgrim::query()->with('identityDocuments');

        $search = isset($filters['search']) ? trim((string) $filters['search']) : '';
        if ($search !== '') {
            $normalized = PilgrimIdentityDocument::normalizeNumber($search);
            $query->where(function ($q) use ($search, $normalized): void {
                $q->where('full_name', 'like', '%'.$search.'%')
                    ->orWhereHas('identityDocuments', fn ($d) => $d->where('normalized_number', $normalized));
            });
        }

        $query->orderBy('full_name')->orderBy('id');

        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return $query->paginate($perPage);
    }
}
