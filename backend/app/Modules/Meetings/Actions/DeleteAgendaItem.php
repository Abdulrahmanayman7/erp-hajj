<?php

namespace App\Modules\Meetings\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Meetings\Exceptions\MeetingDomainException;
use App\Modules\Meetings\Models\Meeting;
use App\Modules\Meetings\Models\MeetingAgendaItem;
use App\Modules\Meetings\Support\MeetingReferenceValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class DeleteAgendaItem
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly MeetingReferenceValidator $references,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, Meeting $meeting, MeetingAgendaItem $agendaItem, Request $request): void
    {
        $tenant = $this->tenantContext->require();

        DB::transaction(function () use ($actor, $meeting, $agendaItem, $request, $tenant): void {
            $locked = Meeting::query()->whereKey($meeting->id)->lockForUpdate()->firstOrFail();
            $this->references->assertMutable($locked);

            $item = MeetingAgendaItem::query()
                ->whereKey($agendaItem->id)
                ->where('meeting_id', $locked->id)
                ->first();

            if ($item === null) {
                throw MeetingDomainException::agendaItemInvalid();
            }

            $snapshot = ['id' => $item->id, 'title' => $item->title];
            $item->delete();

            $this->security->record(AuthorizationSecurityEvent::MEETING_AGENDA_ITEM_DELETED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'meeting_id' => $locked->id,
                'agenda_item' => $snapshot,
            ], $request);
        });
    }
}
