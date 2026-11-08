<?php

namespace App\Modules\SeasonalTemplates\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SeasonalTemplates\Enums\TemplateStatus;
use App\Modules\SeasonalTemplates\Exceptions\SeasonalTemplatesDomainException;
use App\Modules\SeasonalTemplates\Models\SeasonTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ArchiveSeasonTemplate
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * draft|approved → archived. Archived is terminal.
     */
    public function execute(User $actor, SeasonTemplate $template, Request $request): SeasonTemplate
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $template, $request, $tenant): SeasonTemplate {
            $locked = SeasonTemplate::query()->whereKey($template->id)->lockForUpdate()->firstOrFail();

            if (! $locked->status->canArchive()) {
                throw SeasonalTemplatesDomainException::invalidTransition();
            }

            $from = $locked->status;
            $locked->forceFill([
                'status' => TemplateStatus::Archived,
                'archived_at' => now(),
            ])->save();

            $this->security->record(AuthorizationSecurityEvent::SEASON_TEMPLATE_ARCHIVED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'season_template',
                'entity_id' => $locked->id,
                'entity_number' => $locked->template_number,
                'entity_label' => $locked->name,
                'from_status' => $from->value,
                'to_status' => $locked->status->value,
            ], $request);

            return $locked->load(['items', 'createdBy', 'approvedBy']);
        });
    }
}
