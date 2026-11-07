<?php

namespace App\Modules\SeasonalTemplates\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SeasonalTemplates\Enums\TemplateStatus;
use App\Modules\SeasonalTemplates\Models\SeasonTemplate;
use App\Modules\SeasonalTemplates\Support\SafeCopyAllowlist;
use App\Modules\SeasonalTemplates\Support\TemplateItemWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CreateSeasonTemplate
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly TemplateItemWriter $items,
        private readonly CaptureTemplateSnapshotsFromSeason $capture,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array<string, mixed>  $data  name, description?, capture_season_id?, items[]{entity_kind, selection?, snapshot?}
     */
    public function execute(User $actor, array $data, Request $request): SeasonTemplate
    {
        $tenant = $this->tenantContext->require();

        $items = $this->items->normalize((array) ($data['items'] ?? []));

        $captureSeasonId = isset($data['capture_season_id']) ? (int) $data['capture_season_id'] : null;
        if ($captureSeasonId !== null) {
            $items = $this->capture->execute($items, $captureSeasonId);
        }
        $this->items->assertSnapshotRequirements($items);

        return DB::transaction(function () use ($actor, $data, $request, $tenant, $items, $captureSeasonId): SeasonTemplate {
            $template = new SeasonTemplate([
                'name' => trim((string) $data['name']),
                'description' => $data['description'] ?? null,
                'status' => TemplateStatus::Draft,
                'matrix_version' => SafeCopyAllowlist::MATRIX_VERSION,
                'created_by' => $actor->id,
            ]);
            $template->save();

            $this->items->replace($template, $items);

            $this->security->record(AuthorizationSecurityEvent::SEASON_TEMPLATE_CREATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'season_template',
                'entity_id' => $template->id,
                'entity_number' => $template->template_number,
                'entity_label' => $template->name,
                'status' => $template->status->value,
                'matrix_version' => $template->matrix_version,
                'entity_kinds' => array_column($items, 'entity_kind'),
                'snapshot_kinds' => array_column(
                    array_filter($items, static fn (array $item): bool => $item['snapshot'] !== null),
                    'entity_kind',
                ),
                'captured_from_season_id' => $captureSeasonId,
            ], $request);

            return $template->load(['items', 'createdBy', 'approvedBy']);
        });
    }
}
