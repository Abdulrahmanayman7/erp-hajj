<?php

namespace App\Modules\RisksAndComplaints\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\RisksAndComplaints\Enums\ComplaintStatus;
use App\Modules\RisksAndComplaints\Exceptions\RisksAndComplaintsDomainException;
use App\Modules\RisksAndComplaints\Models\OperationalComplaint;
use App\Modules\RisksAndComplaints\Support\RiskReferenceValidator;
use App\Modules\SeasonPilgrims\Models\Season;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CreateComplaint
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly RiskReferenceValidator $references,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, Season $season, array $data, Request $request): OperationalComplaint
    {
        $tenant = $this->tenantContext->require();
        $categories = config('risks_complaints.complaint_categories', []);
        $category = (string) $data['category_code'];
        if (! in_array($category, $categories, true)) {
            throw RisksAndComplaintsDomainException::complaintCategoryInvalid();
        }

        try {
            return DB::transaction(function () use ($actor, $season, $data, $request, $tenant, $category): OperationalComplaint {
                $links = $this->references->resolveOptionalLinks($season, $data);

                $complaint = new OperationalComplaint([
                    'season_id' => $season->id,
                    'title' => trim((string) $data['title']),
                    'body' => (string) $data['body'],
                    'category_code' => $category,
                    'status' => ComplaintStatus::New,
                    'created_by' => $actor->id,
                    'escalation_level' => 0,
                    'season_pilgrim_id' => $links['season_pilgrim_id'],
                    'group_id' => $links['group_id'],
                    'facility_id' => $links['facility_id'],
                    'transport_season_vehicle_id' => $links['transport_season_vehicle_id'],
                    'project_id' => $links['project_id'],
                ]);
                $complaint->save();

                $this->security->record(AuthorizationSecurityEvent::COMPLAINT_CREATED, [
                    'tenant_id' => $tenant->id,
                    'season_id' => $season->id,
                    'actor_id' => $actor->id,
                    'complaint_id' => $complaint->id,
                    'complaint_number' => $complaint->complaint_number,
                    'status' => $complaint->status->value,
                    'after' => [
                        'title' => $complaint->title,
                        'category_code' => $complaint->category_code,
                        'status' => $complaint->status->value,
                    ],
                ], $request);

                return $this->load($complaint);
            });
        } catch (QueryException $e) {
            if ($this->isUniqueNumberViolation($e)) {
                throw RisksAndComplaintsDomainException::complaintNumberTaken();
            }
            throw $e;
        }
    }

    private function load(OperationalComplaint $complaint): OperationalComplaint
    {
        return $complaint->load(['assigneeEmployee', 'createdBy', 'escalatedToEmployee', 'notes.createdBy']);
    }

    private function isUniqueNumberViolation(QueryException $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'operational_complaints_tenant_season_number_unique')
            || (str_contains($message, 'complaint_number') && str_contains($message, 'Duplicate'));
    }
}
