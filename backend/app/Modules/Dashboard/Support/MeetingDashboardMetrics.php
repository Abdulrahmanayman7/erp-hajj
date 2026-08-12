<?php

namespace App\Modules\Dashboard\Support;

use App\Modules\Meetings\Enums\MeetingStatus;
use App\Modules\Meetings\Models\Meeting;
use Illuminate\Support\Collection;

final class MeetingDashboardMetrics
{
    public function __construct(
        private readonly DashboardClock $clock,
    ) {}

    /**
     * @return array{
     *     kpis: array<string, array{value: int, label: string, severity: string, href: string}>,
     *     meetings_today: list<array<string, mixed>>,
     *     meetings_upcoming_7d: list<array<string, mixed>>,
     *     today_ids: list<int>,
     *     starting_soon_entities: Collection<int, Meeting>,
     *     starting_soon_count: int,
     *     today_entities: Collection<int, Meeting>,
     *     today_count: int,
     *     in_progress_entities: Collection<int, Meeting>,
     *     in_progress_count: int
     * }
     */
    public function build(): array
    {
        $now = $this->clock->now();
        $todayStart = $this->clock->today();
        $todayEnd = $todayStart->copy()->endOfDay();
        $minutes = max(1, (int) config('notifications.meeting_starting_soon_minutes', 60));
        $startingUntil = $now->copy()->addMinutes($minutes);
        $upcomingEnd = $todayStart->copy()->addDays(7)->endOfDay();

        $todayQuery = Meeting::query()
            ->whereIn('status', [MeetingStatus::Scheduled, MeetingStatus::InProgress])
            ->whereNotNull('scheduled_at')
            ->whereBetween('scheduled_at', [$todayStart, $todayEnd]);

        $todayCount = (clone $todayQuery)->count();
        $todayMeetings = (clone $todayQuery)
            ->orderBy('scheduled_at')
            ->orderBy('id')
            ->limit(8)
            ->get();
        $todayIds = $todayMeetings->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $startingSoonQuery = Meeting::query()
            ->where('status', MeetingStatus::Scheduled)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '>', $now)
            ->where('scheduled_at', '<=', $startingUntil);

        $startingSoonCount = (clone $startingSoonQuery)->count();
        $startingSoonIds = (clone $startingSoonQuery)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
        $startingSoonEntities = (clone $startingSoonQuery)
            ->orderBy('scheduled_at')
            ->orderBy('id')
            ->limit(3)
            ->get();

        $todayEntitiesQuery = (clone $todayQuery)->orderBy('scheduled_at')->orderBy('id');
        if ($startingSoonIds !== []) {
            $todayEntitiesQuery->whereNotIn('id', $startingSoonIds);
        }
        $todayNotSoonCount = (clone $todayEntitiesQuery)->count();
        $todayEntities = $todayEntitiesQuery->limit(3)->get();

        $inProgressQuery = Meeting::query()
            ->where('status', MeetingStatus::InProgress);

        $inProgressCount = (clone $inProgressQuery)->count();
        $inProgressEntities = (clone $inProgressQuery)
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->limit(3)
            ->get();

        $upcomingQuery = Meeting::query()
            ->where('status', MeetingStatus::Scheduled)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '>', $now)
            ->where('scheduled_at', '<=', $upcomingEnd);

        if ($todayIds !== []) {
            $upcomingQuery->whereNotIn('id', $todayIds);
        }

        $upcoming = $upcomingQuery
            ->orderBy('scheduled_at')
            ->orderBy('id')
            ->limit(8)
            ->get()
            ->map(fn (Meeting $meeting): array => $this->compactMeeting($meeting))
            ->all();

        return [
            'kpis' => [
                'meetings_today' => DashboardKpi::make(
                    $todayCount,
                    'اجتماعات اليوم',
                    'info',
                    DashboardLinks::meetings(),
                ),
                'meetings_in_progress' => DashboardKpi::make(
                    $inProgressCount,
                    'اجتماعات جارية',
                    'info',
                    DashboardLinks::meetings(['status' => 'in_progress']),
                ),
            ],
            'meetings_today' => $todayMeetings
                ->map(fn (Meeting $meeting): array => $this->compactMeeting($meeting))
                ->all(),
            'meetings_upcoming_7d' => $upcoming,
            'today_ids' => $todayIds,
            'starting_soon_entities' => $startingSoonEntities,
            'starting_soon_count' => $startingSoonCount,
            'today_entities' => $todayEntities,
            'today_count' => $todayCount,
            'today_not_soon_count' => $todayNotSoonCount,
            'in_progress_entities' => $inProgressEntities,
            'in_progress_count' => $inProgressCount,
            'starting_soon_ids' => $startingSoonIds,
        ];
    }

    /**
     * @return array{id: int, number: string, title: string, scheduled_at: string|null, status: string, href: string}
     */
    private function compactMeeting(Meeting $meeting): array
    {
        return [
            'id' => (int) $meeting->id,
            'number' => (string) $meeting->meeting_number,
            'title' => (string) $meeting->title,
            'scheduled_at' => $meeting->scheduled_at?->toIso8601String(),
            'status' => $meeting->status->value,
            'href' => DashboardLinks::meeting((int) $meeting->id),
        ];
    }
}
