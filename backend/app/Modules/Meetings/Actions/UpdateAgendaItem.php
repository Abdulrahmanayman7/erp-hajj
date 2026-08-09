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

final class UpdateAgendaItem
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly MeetingReferenceValidator $references,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(
        User $actor,
        Meeting $meeting,
        MeetingAgendaItem $agendaItem,
        array $data,
        Request $request,
    ): MeetingAgendaItem {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $meeting, $agendaItem, $data, $request, $tenant): MeetingAgendaItem {
            $locked = Meeting::query()->whereKey($meeting->id)->lockForUpdate()->firstOrFail();
            $this->references->assertMutable($locked);

            $item = MeetingAgendaItem::query()
                ->whereKey($agendaItem->id)
                ->where('meeting_id', $locked->id)
                ->lockForUpdate()
                ->first();

            if ($item === null) {
                throw MeetingDomainException::agendaItemInvalid();
            }

            if (array_key_exists('title', $data)) {
                $item->title = $data['title'];
            }
            if (array_key_exists('description', $data)) {
                $item->description = $data['description'];
            }
            if (array_key_exists('sort_order', $data)) {
                $item->sort_order = (int) $data['sort_order'];
            }
            $item->save();

            $this->security->record(AuthorizationSecurityEvent::MEETING_AGENDA_ITEM_UPDATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'meeting_id' => $locked->id,
                'agenda_item_id' => $item->id,
            ], $request);

            return $item;
        });
    }
}
