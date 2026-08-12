<?php

namespace App\Modules\Notifications\Console;

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantContext;
use App\Core\Tenancy\TenantStatus;
use App\Modules\Meetings\Enums\MeetingStatus;
use App\Modules\Meetings\Models\Meeting;
use App\Modules\Notifications\Enums\NotificationType;
use App\Modules\Notifications\Support\NotificationDispatcher;
use App\Modules\Notifications\Support\NotificationRecipientResolver;
use Illuminate\Console\Command;

class ScanMeetingsSoonCommand extends Command
{
    protected $signature = 'notifications:scan-meetings-soon';

    protected $description = 'Notify about meetings starting soon (daily bucket per meeting)';

    public function handle(
        TenantContext $tenantContext,
        NotificationDispatcher $dispatcher,
        NotificationRecipientResolver $recipients,
    ): int {
        $count = 0;

        Tenant::query()
            ->where('status', TenantStatus::Active)
            ->orderBy('id')
            ->chunkById(50, function ($tenants) use ($tenantContext, $dispatcher, $recipients, &$count): void {
                foreach ($tenants as $tenant) {
                    /** @var Tenant $tenant */
                    $tenantContext->runAsTenant($tenant, function () use ($tenant, $dispatcher, $recipients, &$count): void {
                        $timezone = $tenant->timezone ?: config('app.timezone', 'Asia/Riyadh');
                        $now = now($timezone);
                        $minutes = max(1, (int) config('notifications.meeting_starting_soon_minutes', 60));
                        $until = $now->copy()->addMinutes($minutes);
                        $bucket = $now->toDateString();

                        Meeting::query()
                            ->with('attendees')
                            ->where('status', MeetingStatus::Scheduled)
                            ->whereNotNull('scheduled_at')
                            ->where('scheduled_at', '>', $now)
                            ->where('scheduled_at', '<=', $until)
                            ->orderBy('id')
                            ->chunkById(100, function ($meetings) use ($dispatcher, $recipients, $bucket, &$count): void {
                                foreach ($meetings as $meeting) {
                                    /** @var Meeting $meeting */
                                    $ids = [];
                                    foreach ($meeting->attendees as $attendee) {
                                        $user = $recipients->resolveUserFromEmployeeId((int) $attendee->employee_id);
                                        if ($user !== null) {
                                            $ids[] = (int) $user->id;
                                        }
                                    }

                                    if ($ids === []) {
                                        continue;
                                    }

                                    $dispatcher->notify(
                                        type: NotificationType::MeetingStartingSoon,
                                        recipientUsers: $ids,
                                        entityType: 'meeting',
                                        entityId: (int) $meeting->id,
                                        dedupeBucket: $bucket,
                                        context: ['number' => $meeting->meeting_number],
                                    );
                                    $count++;
                                }
                            });
                    });
                }
            });

        $this->info("Scanned meetings starting soon; sets={$count}.");

        return self::SUCCESS;
    }
}
