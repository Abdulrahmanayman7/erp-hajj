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
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array<string, mixed>  $data  name, description?, items[]{entity_kind, selection?}
     */
    public function execute(User $actor, array $data, Request $request): SeasonTemplate
    {
        $tenant = $this->tenantContext->require();

        /** @var list<array{entity_kind: string, selection: array<string, mixed>|null}> $items */
        $items = $this->items->normalize((array) ($data['items'] ?? []));

        return DB::transaction(function () use ($actor, $data, $request, $tenant, $items): SeasonTemplate {
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
            ], $request);

            return $template->load(['items', 'createdBy', 'approvedBy']);
        });
    }
}
