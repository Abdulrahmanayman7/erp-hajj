<?php

namespace App\Modules\SeasonalTemplates\Actions;

use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SeasonalTemplates\Exceptions\SeasonalTemplatesDomainException;
use App\Modules\SeasonalTemplates\Models\SeasonTemplate;
use App\Modules\SeasonalTemplates\Support\TemplateItemWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class UpdateSeasonTemplate
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly TemplateItemWriter $items,
        private readonly CaptureTemplateSnapshotsFromSeason $capture,
    ) {}

    /**
     * Only draft templates are editable; approved/archived templates are immutable.
     *
     * @param  array<string, mixed>  $data  name?, description?, items?[], capture_season_id? (with items)
     */
    public function execute(User $actor, SeasonTemplate $template, array $data, Request $request): SeasonTemplate
    {
        $this->tenantContext->require();

        $items = array_key_exists('items', $data)
            ? $this->items->normalize((array) $data['items'])
            : null;

        if ($items !== null) {
            if (isset($data['capture_season_id'])) {
                $items = $this->capture->execute($items, (int) $data['capture_season_id']);
            }
            $items = $this->keepExistingSnapshots($template, $items);
            $this->items->assertSnapshotRequirements($items);
        }

        return DB::transaction(function () use ($template, $data, $items): SeasonTemplate {
            $locked = SeasonTemplate::query()->whereKey($template->id)->lockForUpdate()->firstOrFail();

            if (! $locked->status->isEditable()) {
                throw SeasonalTemplatesDomainException::invalidTransition('لا يمكن تعديل قالب غير مسودة.');
            }

            if (array_key_exists('name', $data)) {
                $locked->name = trim((string) $data['name']);
            }
            if (array_key_exists('description', $data)) {
                $locked->description = $data['description'];
            }
            $locked->save();

            if ($items !== null) {
                $this->items->replace($locked, $items);
            }

            return $locked->load(['items', 'createdBy', 'approvedBy']);
        });
    }

    /**
     * Editing the selection must not silently drop a snapshot the client cannot see
     * (rows are never echoed back): an item that stays in the template without a new
     * or re-captured snapshot keeps the one it already has. Removing the kind removes it.
     *
     * @param  list<array{entity_kind: string, selection: array<string, mixed>|null, snapshot: array<string, mixed>|null}>  $items
     * @return list<array{entity_kind: string, selection: array<string, mixed>|null, snapshot: array<string, mixed>|null}>
     */
    private function keepExistingSnapshots(SeasonTemplate $template, array $items): array
    {
        $existing = $template->items()->get()->keyBy('entity_kind');

        return array_map(static function (array $item) use ($existing): array {
            if ($item['snapshot'] === null && $existing->has($item['entity_kind'])) {
                $item['snapshot'] = $existing->get($item['entity_kind'])->snapshot_json;
            }

            return $item;
        }, $items);
    }
}
