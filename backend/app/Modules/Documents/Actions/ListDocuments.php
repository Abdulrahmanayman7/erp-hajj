<?php

namespace App\Modules\Documents\Actions;

use App\Core\Tenancy\TenantContext;
use App\Modules\Documents\Enums\DocumentStatus;
use App\Modules\Documents\Models\Document;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListDocuments
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Document>
     */
    public function execute(array $filters): LengthAwarePaginator
    {
        $this->tenantContext->require();

        $query = Document::query()->with(['category', 'uploader', 'linkable']);

        $search = isset($filters['search']) ? trim((string) $filters['search']) : '';
        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('document_number', 'like', '%'.$search.'%')
                    ->orWhere('title', 'like', '%'.$search.'%')
                    ->orWhere('original_filename', 'like', '%'.$search.'%');
            });
        }

        $status = $filters['status'] ?? null;
        if ($status === null || $status === '') {
            $query->where('status', DocumentStatus::Active->value);
        } elseif (is_string($status) && $status !== 'all') {
            $query->where('status', $status);
        }

        if (! empty($filters['category_id'])) {
            $query->where('category_id', (int) $filters['category_id']);
        }

        if (! empty($filters['uploaded_by'])) {
            $query->where('uploaded_by', (int) $filters['uploaded_by']);
        }

        if (! empty($filters['linkable_type'])) {
            $query->where('linkable_type', (string) $filters['linkable_type']);
        }

        if (! empty($filters['linkable_id'])) {
            $query->where('linkable_id', (int) $filters['linkable_id']);
        }

        if (! empty($filters['uploaded_from'])) {
            $query->whereDate('created_at', '>=', (string) $filters['uploaded_from']);
        }
        if (! empty($filters['uploaded_to'])) {
            $query->whereDate('created_at', '<=', (string) $filters['uploaded_to']);
        }

        $sort = is_string($filters['sort'] ?? null) ? (string) $filters['sort'] : 'created_at';
        $direction = strtolower((string) ($filters['direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['created_at', 'document_number', 'title', 'size_bytes'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'created_at';
        }

        $query->orderBy($sort, $direction)->orderBy('id', 'desc');

        $perPage = (int) ($filters['per_page'] ?? 15);
        $perPage = max(1, min($perPage, 100));

        return $query->paginate($perPage);
    }
}
