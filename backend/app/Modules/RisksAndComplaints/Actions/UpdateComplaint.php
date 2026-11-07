<?php

namespace App\Modules\RisksAndComplaints\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\RisksAndComplaints\Enums\ComplaintStatus;
use App\Modules\RisksAndComplaints\Exceptions\RisksAndComplaintsDomainException;
use App\Modules\RisksAndComplaints\Models\OperationalComplaint;
use App\Modules\RisksAndComplaints\Support\ComplaintLifecycle;
use App\Modules\RisksAndComplaints\Support\RiskReferenceValidator;
use App\Modules\SeasonPilgrims\Context\SeasonContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class UpdateComplaint
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly SeasonContext $seasonContext,
        private readonly RiskReferenceValidator $references,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, OperationalComplaint $complaint, array $data, Request $request): OperationalComplaint
    {
        $tenant = $this->tenantContext->require();
        $season = $this->seasonContext->require();

        return DB::transaction(function () use ($actor, $complaint, $data, $request, $tenant, $season): OperationalComplaint {
            $locked = OperationalComplaint::query()->whereKey($complaint->id)->lockForUpdate()->firstOrFail();
            ComplaintLifecycle::assertContentEditable($locked->status);

            $before = [
                'title' => $locked->title,
                'body' => $locked->body,
                'category_code' => $locked->category_code,
                'status' => $locked->status->value,
                'season_pilgrim_id' => $locked->season_pilgrim_id,
                'group_id' => $locked->group_id,
                'facility_id' => $locked->facility_id,
                'transport_season_vehicle_id' => $locked->transport_season_vehicle_id,
                'project_id' => $locked->project_id,
            ];

            if (array_key_exists('title', $data) && $data['title'] !== null) {
                $locked->title = trim((string) $data['title']);
            }
            if (array_key_exists('body', $data) && $data['body'] !== null) {
                $locked->body = (string) $data['body'];
            }
            if (array_key_exists('category_code', $data) && $data['category_code'] !== null) {
                $categories = config('risks_complaints.complaint_categories', []);
                $category = (string) $data['category_code'];
                if (! in_array($category, $categories, true)) {
                    throw RisksAndComplaintsDomainException::complaintCategoryInvalid();
                }
                $locked->category_code = $category;
            }

            $links = $this->references->resolveOptionalLinks($season, $data, partial: true);
            foreach (['season_pilgrim_id', 'group_id', 'facility_id', 'transport_season_vehicle_id', 'project_id'] as $field) {
                if (array_key_exists($field, $data)) {
                    $locked->{$field} = $links[$field];
                }
            }

            $startReview = ($data['start_review'] ?? false) === true
                || ($data['start_review'] ?? null) === 1
                || ($data['start_review'] ?? null) === '1'
                || (($data['status'] ?? null) === ComplaintStatus::UnderReview->value);

            if ($startReview) {
                ComplaintLifecycle::assertCanStartReview($locked->status);
                $locked->status = ComplaintStatus::UnderReview;
            }

            $locked->save();

            $this->security->record(AuthorizationSecurityEvent::COMPLAINT_UPDATED, [
                'tenant_id' => $tenant->id,
                'season_id' => $locked->season_id,
                'actor_id' => $actor->id,
                'complaint_id' => $locked->id,
                'complaint_number' => $locked->complaint_number,
                'before' => $before,
                'after' => [
                    'title' => $locked->title,
                    'body' => $locked->body,
                    'category_code' => $locked->category_code,
                    'status' => $locked->status->value,
                    'season_pilgrim_id' => $locked->season_pilgrim_id,
                    'group_id' => $locked->group_id,
                    'facility_id' => $locked->facility_id,
                    'transport_season_vehicle_id' => $locked->transport_season_vehicle_id,
                    'project_id' => $locked->project_id,
                ],
            ], $request);

            return $locked->load(['assigneeEmployee', 'createdBy', 'escalatedToEmployee', 'notes.createdBy']);
        });
    }
}
