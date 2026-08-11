<?php

namespace App\Modules\Meetings\Actions;

use App\Core\Tenancy\TenantContext;
use App\Modules\Meetings\Enums\MeetingStatus;
use App\Modules\Meetings\Models\Meeting;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

final class ListMeetings
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Meeting>
     */
    public function execute(array $filters): LengthAwarePaginator
    {
        $tenant = $this->tenantContext->require();
        $timezone = $tenant->timezone ?: config('app.timezone', 'Asia/Riyadh');
        $now = now($timezone);

        $query = Meeting::query()
            ->with(['organizationUnit', 'chairperson', 'secretary', 'createdBy'])
            ->withCount('attendees');

        $search = isset($filters['search']) ? trim((string) $filters['search']) : '';
        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('title', 'like', '%'.$search.'%')
                    ->orWhere('meeting_number', 'like', '%'.$search.'%');
            });
        }

        $status = isset($filters['status']) ? trim((string) $filters['status']) : '';
        if ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        if (! empty($filters['organization_unit_id'])) {
            $query->where('organization_unit_id', (int) $filters['organization_unit_id']);
        }

        if (! empty($filters['chairperson_employee_id'])) {
            $query->where('chairperson_employee_id', (int) $filters['chairperson_employee_id']);
        }

        if (! empty($filters['date_from'])) {
            $from = Carbon::parse((string) $filters['date_from'], $timezone)->startOfDay()->utc();
            $query->where('scheduled_at', '>=', $from);
        }

        if (! empty($filters['date_to'])) {
            $to = Carbon::parse((string) $filters['date_to'], $timezone)->endOfDay()->utc();
            $query->where('scheduled_at', '<=', $to);
        }

        $upcoming = $filters['upcoming'] ?? null;
        if ($upcoming === 1 || $upcoming === '1' || $upcoming === true || $upcoming === 'true') {
            $query->where('status', MeetingStatus::Scheduled->value)
                ->where('scheduled_at', '>=', $now->copy()->utc());
        }

        $past = $filters['past'] ?? null;
        if ($past === 1 || $past === '1' || $past === true || $past === 'true') {
            $query->where(function ($q) use ($now): void {
                $q->whereIn('status', [
                    MeetingStatus::Completed->value,
                    MeetingStatus::Cancelled->value,
                ])->orWhere(function ($q2) use ($now): void {
                    $q2->where('status', MeetingStatus::Scheduled->value)
                        ->where('scheduled_at', '<', $now->copy()->utc());
                });
            });
        }

        $sort = $filters['sort'] ?? null;
        $allowedSorts = ['scheduled_at', 'created_at', 'title', 'meeting_number', 'status'];
        if ($sort !== null && in_array($sort, $allowedSorts, true)) {
            $direction = strtolower((string) ($filters['direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
            $query->orderBy($sort, $direction)->orderBy('id', 'desc');
        } else {
            // Nulls last so scheduled meetings surface first (MySQL: IS NULL ASC).
            $query->orderByRaw('scheduled_at IS NULL ASC')
                ->orderBy('scheduled_at', 'desc')
                ->orderBy('id', 'desc');
        }

        $perPage = (int) ($filters['per_page'] ?? 15);
        $perPage = max(1, min($perPage, 100));

        return $query->paginate($perPage);
    }
}
