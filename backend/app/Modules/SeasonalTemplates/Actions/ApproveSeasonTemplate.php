<?php

namespace App\Modules\SeasonalTemplates\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SeasonalTemplates\Enums\TemplateStatus;
use App\Modules\SeasonalTemplates\Exceptions\SeasonalTemplatesDomainException;
use App\Modules\SeasonalTemplates\Models\SeasonTemplate;
use App\Modules\SeasonalTemplates\Support\SafeCopyAllowlist;
use App\Modules\SeasonalTemplates\Support\TemplateItemWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ApproveSeasonTemplate
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly TemplateItemWriter $items,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * draft → approved; pins the allowlist matrix version.
     */
    public function execute(User $actor, SeasonTemplate $template, Request $request): SeasonTemplate
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $template, $request, $tenant): SeasonTemplate {
            $locked = SeasonTemplate::query()->with('items')->whereKey($template->id)->lockForUpdate()->firstOrFail();

            if (! $locked->status->canApprove()) {
                throw SeasonalTemplatesDomainException::invalidTransition();
            }

            $this->items->assertTemplateKindsApproved($locked);

            $from = $locked->status;
            $locked->forceFill([
                'status' => TemplateStatus::Approved,
                'matrix_version' => SafeCopyAllowlist::MATRIX_VERSION,
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ])->save();

            $this->security->record(AuthorizationSecurityEvent::SEASON_TEMPLATE_APPROVED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'season_template',
                'entity_id' => $locked->id,
                'entity_number' => $locked->template_number,
                'entity_label' => $locked->name,
                'from_status' => $from->value,
                'to_status' => $locked->status->value,
                'matrix_version' => $locked->matrix_version,
            ], $request);

            return $locked->load(['items', 'createdBy', 'approvedBy']);
        });
    }
}
