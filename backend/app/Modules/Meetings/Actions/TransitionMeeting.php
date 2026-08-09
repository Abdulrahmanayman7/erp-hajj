<?php

namespace App\Modules\Meetings\Actions;

use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Meetings\Enums\MeetingStatus;
use App\Modules\Meetings\Exceptions\MeetingDomainException;
use App\Modules\Meetings\Models\Meeting;
use App\Modules\Meetings\Support\MeetingLifecycle;
use App\Modules\Meetings\Support\MeetingTransitionRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Shared atomic lifecycle transition: lock → validate → update → history → audit.
 */
final class TransitionMeeting
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly MeetingTransitionRecorder $transitions,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array<string, mixed>  $auditExtra
     * @param  callable(Meeting): void|null  $beforeSave
     */
    public function execute(
        ?User $actor,
        Meeting $meeting,
        MeetingStatus $to,
        ?string $comment,
        string $auditEvent,
        ?Request $request = null,
        array $auditExtra = [],
        ?callable $beforeSave = null,
    ): Meeting {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $meeting, $to, $comment, $auditEvent, $request, $auditExtra, $beforeSave, $tenant): Meeting {
            $locked = Meeting::query()->whereKey($meeting->id)->lockForUpdate()->firstOrFail();
            $from = $locked->status;

            MeetingLifecycle::assertAllowed($from, $to);

            if (MeetingLifecycle::requiresComment($from, $to) && ($comment === null || trim($comment) === '')) {
                throw MeetingDomainException::commentRequired();
            }

            if ($beforeSave !== null) {
                $beforeSave($locked);
            }

            $locked->status = $to;
            $locked->save();

            $this->transitions->record($locked, $from, $to, $actor, $comment !== null ? trim($comment) : null, $request);

            $this->security->record($auditEvent, array_merge([
                'tenant_id' => $tenant->id,
                'actor_id' => $actor?->id,
                'meeting_id' => $locked->id,
                'meeting_number' => $locked->meeting_number,
                'from_status' => $from->value,
                'to_status' => $to->value,
            ], $auditExtra), $request);

            return $this->loadRelations($locked);
        });
    }

    private function loadRelations(Meeting $meeting): Meeting
    {
        return $meeting->load([
            'organizationUnit',
            'chairperson',
            'secretary',
            'createdBy',
            'attendees.employee',
            'agendaItems',
            'recommendations.owner',
            'recommendations.createdBy',
            'statusTransitions.actor',
        ]);
    }
}
