<?php

namespace App\Modules\SeasonalTemplates\Support;

use App\Modules\SeasonalTemplates\Exceptions\SeasonalTemplatesDomainException;
use App\Modules\SeasonalTemplates\Models\SeasonTemplate;
use App\Modules\SeasonalTemplates\Models\SeasonTemplateItem;

/**
 * Validates template items against the allowlist (deny by default) and persists them.
 */
final class TemplateItemWriter
{
    public function __construct(
        private readonly SnapshotNormalizer $snapshots,
    ) {}

    /**
     * @param  array<int, mixed>  $items
     * @return list<array{entity_kind: string, selection: array<string, mixed>|null, snapshot: array{anchor: string|null, rows: array<string, array<string, mixed>>}|null}>
     */
    public function normalize(array $items): array
    {
        $seen = [];
        $normalized = [];

        foreach ($items as $item) {
            $kind = is_array($item) ? (string) ($item['entity_kind'] ?? '') : '';
            SafeCopyAllowlist::assertApproved($kind);

            if (isset($seen[$kind])) {
                continue;
            }
            $seen[$kind] = true;

            $codes = is_array($item['selection'] ?? null) && is_array($item['selection']['codes'] ?? null)
                ? array_values(array_unique(array_map('strval', $item['selection']['codes'])))
                : [];

            $normalized[] = [
                'entity_kind' => $kind,
                'selection' => $codes === [] ? null : ['codes' => $codes],
                'snapshot' => $this->snapshots->normalize($kind, $item['snapshot'] ?? null),
            ];
        }

        $order = array_flip(SafeCopyAllowlist::kinds());
        usort($normalized, static fn (array $a, array $b): int => $order[$a['entity_kind']] <=> $order[$b['entity_kind']]);

        return $normalized;
    }

    /**
     * Snapshot-only kinds (contract definitions) have no live source, so an item
     * without snapshot rows would be unusable.
     *
     * @param  list<array{entity_kind: string, snapshot: array<string, mixed>|null}>  $items
     */
    public function assertSnapshotRequirements(array $items): void
    {
        foreach ($items as $item) {
            if (SafeCopyAllowlist::isSnapshotOnly($item['entity_kind']) && $item['snapshot'] === null) {
                throw SeasonalTemplatesDomainException::snapshotInvalid(
                    'هذا النوع يتطلب صفوف لقطة داخل القالب: '.$item['entity_kind'],
                );
            }
        }
    }

    /**
     * @param  list<array{entity_kind: string, selection: array<string, mixed>|null, snapshot: array<string, mixed>|null}>  $items
     */
    public function replace(SeasonTemplate $template, array $items): void
    {
        SeasonTemplateItem::query()->where('season_template_id', $template->id)->delete();

        foreach ($items as $index => $item) {
            (new SeasonTemplateItem([
                'season_template_id' => $template->id,
                'entity_kind' => $item['entity_kind'],
                'sort_order' => $index,
                'selection_json' => $item['selection'],
                'snapshot_json' => $item['snapshot'],
            ]))->save();
        }
    }

    public function assertTemplateKindsApproved(SeasonTemplate $template): void
    {
        if ($template->items->isEmpty()) {
            throw SeasonalTemplatesDomainException::invalidTransition('لا يمكن اعتماد قالب بلا عناصر.');
        }

        foreach ($template->items as $item) {
            SafeCopyAllowlist::assertApproved($item->entity_kind);

            if (SafeCopyAllowlist::isSnapshotOnly($item->entity_kind) && ($item->snapshot_json['rows'] ?? []) === []) {
                throw SeasonalTemplatesDomainException::invalidTransition('لا يمكن اعتماد قالب بنوع يتطلب لقطة دون صفوف.');
            }
        }
    }
}
