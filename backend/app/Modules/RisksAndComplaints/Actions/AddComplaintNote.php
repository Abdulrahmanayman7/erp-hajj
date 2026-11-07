<?php

namespace App\Modules\RisksAndComplaints\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\RisksAndComplaints\Models\OperationalComplaint;
use App\Modules\RisksAndComplaints\Models\OperationalComplaintNote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AddComplaintNote
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array{body: string}  $data
     */
    public function execute(User $actor, OperationalComplaint $complaint, array $data, Request $request): OperationalComplaint
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $complaint, $data, $request, $tenant): OperationalComplaint {
            $locked = OperationalComplaint::query()->whereKey($complaint->id)->lockForUpdate()->firstOrFail();

            $note = OperationalComplaintNote::query()->create([
                'complaint_id' => $locked->id,
                'body' => (string) $data['body'],
                'created_by' => $actor->id,
                'created_at' => now(),
            ]);

            $this->security->record(AuthorizationSecurityEvent::COMPLAINT_NOTE_ADDED, [
                'tenant_id' => $tenant->id,
                'season_id' => $locked->season_id,
                'actor_id' => $actor->id,
                'complaint_id' => $locked->id,
                'complaint_number' => $locked->complaint_number,
                'note_id' => $note->id,
                'after' => [
                    'body' => $note->body,
                    'created_by' => $note->created_by,
                    'created_at' => $note->created_at?->toIso8601String(),
                ],
            ], $request);

            return $locked->load(['assigneeEmployee', 'createdBy', 'escalatedToEmployee', 'notes.createdBy']);
        });
    }
}
