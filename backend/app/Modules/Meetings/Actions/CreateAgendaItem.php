<?php

namespace App\Modules\Meetings\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Meetings\Models\Meeting;
use App\Modules\Meetings\Models\MeetingAgendaItem;
use App\Modules\Meetings\Support\MeetingReferenceValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CreateAgendaItem
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly MeetingReferenceValidator $references,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, Meeting $meeting, array $data, Request $request): MeetingAgendaItem
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $meeting, $data, $request, $tenant): MeetingAgendaItem {
            $locked = Meeting::query()->whereKey($meeting->id)->lockForUpdate()->firstOrFail();
            $this->references->assertMutable($locked);

            $item = new MeetingAgendaItem([
                'meeting_id' => $locked->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'sort_order' => (int) ($data['sort_order'] ?? 0),
            ]);
            $item->save();

            $this->security->record(AuthorizationSecurityEvent::MEETING_AGENDA_ITEM_CREATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'meeting_id' => $locked->id,
                'agenda_item_id' => $item->id,
            ], $request);

            return $item;
        });
    }
}
