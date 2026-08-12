<?php

namespace App\Modules\Dashboard\Support;

use App\Modules\Assets\Models\AssetCustody;
use App\Modules\Contracts\Models\Contract;
use App\Modules\Decisions\Models\Decision;
use App\Modules\Inventory\Models\InventoryBalance;
use App\Modules\Meetings\Models\Meeting;
use App\Modules\Tasks\Models\Task;
use Illuminate\Support\Collection;

final class AttentionBuilder
{
    private const MAX_ITEMS = 15;

    /**
     * @param  array<string, mixed>  $bundle  Metrics keyed by section
     * @return list<array<string, mixed>>
     */
    public function build(array $bundle): array
    {
        $items = [];

        $append = function (array $chunk) use (&$items): void {
            foreach ($chunk as $item) {
                if (count($items) >= self::MAX_ITEMS) {
                    return;
                }
                $items[] = $item;
            }
        };

        if (isset($bundle['tasks'])) {
            $append($this->fromCountAndEntities(
                type: 'TASK_OVERDUE',
                severity: 'critical',
                count: (int) $bundle['tasks']['overdue_count'],
                entities: $bundle['tasks']['overdue_entities'],
                groupedTitle: fn (int $n): string => "{$n} مهام متأخرة",
                groupedSubtitle: 'تتطلب متابعة فورية',
                groupedHref: DashboardLinks::tasks(['overdue' => 1]),
                entityType: 'task',
                entityTitle: fn (Task $t): string => (string) $t->title,
                entitySubtitle: fn (Task $t): string => (string) $t->task_number,
                entityHref: fn (Task $t): string => DashboardLinks::task((int) $t->id),
            ));
        }

        if (isset($bundle['custodies'])) {
            $append($this->fromCountAndEntities(
                type: 'CUSTODY_OVERDUE',
                severity: 'critical',
                count: (int) $bundle['custodies']['overdue_count'],
                entities: $bundle['custodies']['overdue_entities'],
                groupedTitle: fn (int $n): string => "{$n} عُهد متأخرة",
                groupedSubtitle: 'تجاوزت موعد الإرجاع المتوقع',
                groupedHref: DashboardLinks::assets(['overdue' => 1]),
                entityType: 'custody',
                entityTitle: fn (AssetCustody $c): string => (string) $c->custody_number,
                entitySubtitle: 'عهدة متأخرة',
                entityHref: fn (AssetCustody $c): string => DashboardLinks::asset((int) $c->asset_id),
                entityId: fn (AssetCustody $c): int => (int) $c->id,
            ));
        }

        if (isset($bundle['contracts'])) {
            $append($this->fromCountAndEntities(
                type: 'CONTRACT_EXPIRED',
                severity: 'critical',
                count: (int) $bundle['contracts']['expired_count'],
                entities: $bundle['contracts']['expired_entities']->take(3),
                groupedTitle: fn (int $n): string => "{$n} عقود منتهية",
                groupedSubtitle: 'تحتاج مراجعة الحالة',
                groupedHref: DashboardLinks::contracts(['status' => 'expired']),
                entityType: 'contract',
                entityTitle: fn (Contract $c): string => (string) $c->title,
                entitySubtitle: fn (Contract $c): string => (string) $c->contract_number,
                entityHref: fn (Contract $c): string => DashboardLinks::contract((int) $c->id),
            ));
        }

        if (isset($bundle['inventory'])) {
            $append($this->fromCountAndEntities(
                type: 'INVENTORY_OUT',
                severity: 'critical',
                count: (int) $bundle['inventory']['out_count'],
                entities: $bundle['inventory']['out_entities'],
                groupedTitle: fn (int $n): string => "{$n} أرصدة نافدة",
                groupedSubtitle: 'نفاد المخزون',
                groupedHref: DashboardLinks::inventory(['stock_state' => 'out_of_stock']),
                entityType: 'inventory_balance',
                entityTitle: fn (InventoryBalance $b): string => (string) ($b->item?->name ?? 'صنف'),
                entitySubtitle: fn (InventoryBalance $b): string => (string) ($b->warehouse?->name ?? ''),
                entityHref: fn (InventoryBalance $b): string => DashboardLinks::inventory(['stock_state' => 'out_of_stock']),
            ));
        }

        if (isset($bundle['decisions'])) {
            $append($this->fromCountAndEntities(
                type: 'DECISION_PENDING_APPROVAL',
                severity: 'warning',
                count: (int) $bundle['decisions']['pending_count'],
                entities: $bundle['decisions']['pending_entities'],
                groupedTitle: fn (int $n): string => "{$n} قرارات بانتظار الموافقة",
                groupedSubtitle: 'بانتظار الاعتماد',
                groupedHref: DashboardLinks::decisions(['status' => 'pending_approval']),
                entityType: 'decision',
                entityTitle: fn (Decision $d): string => (string) $d->title,
                entitySubtitle: fn (Decision $d): string => (string) $d->decision_number,
                entityHref: fn (Decision $d): string => DashboardLinks::decision((int) $d->id),
            ));
        }

        if (isset($bundle['contracts'])) {
            $append($this->fromCountAndEntities(
                type: 'CONTRACT_EXPIRING_SOON',
                severity: 'warning',
                count: (int) $bundle['contracts']['expiring_count'],
                entities: $bundle['contracts']['expiring_entities'],
                groupedTitle: fn (int $n): string => "{$n} عقود تنتهي قريبًا",
                groupedSubtitle: 'ضمن نافذة الانتهاء القريب',
                groupedHref: DashboardLinks::contracts(['expiring_soon' => 1]),
                entityType: 'contract',
                entityTitle: fn (Contract $c): string => (string) $c->title,
                entitySubtitle: fn (Contract $c): string => (string) $c->contract_number,
                entityHref: fn (Contract $c): string => DashboardLinks::contract((int) $c->id),
            ));
        }

        if (isset($bundle['tasks'])) {
            $append($this->fromCountAndEntities(
                type: 'TASK_DUE_SOON',
                severity: 'warning',
                count: (int) $bundle['tasks']['due_soon_count'],
                entities: $bundle['tasks']['due_soon_entities'],
                groupedTitle: fn (int $n): string => "{$n} مهام مستحقة قريبًا",
                groupedSubtitle: 'ضمن نافذة الاستحقاق القريب',
                groupedHref: DashboardLinks::tasks(),
                entityType: 'task',
                entityTitle: fn (Task $t): string => (string) $t->title,
                entitySubtitle: fn (Task $t): string => (string) $t->task_number,
                entityHref: fn (Task $t): string => DashboardLinks::task((int) $t->id),
            ));
        }

        if (isset($bundle['custodies'])) {
            $append($this->fromCountAndEntities(
                type: 'CUSTODY_DUE_SOON',
                severity: 'warning',
                count: (int) $bundle['custodies']['due_soon_count'],
                entities: $bundle['custodies']['due_soon_entities'],
                groupedTitle: fn (int $n): string => "{$n} عُهد يقترب موعد إرجاعها",
                groupedSubtitle: 'ضمن نافذة الإرجاع القريب',
                groupedHref: DashboardLinks::assets(),
                entityType: 'custody',
                entityTitle: fn (AssetCustody $c): string => (string) $c->custody_number,
                entitySubtitle: 'عهدة قريبة الإرجاع',
                entityHref: fn (AssetCustody $c): string => DashboardLinks::asset((int) $c->asset_id),
                entityId: fn (AssetCustody $c): int => (int) $c->id,
            ));
        }

        if (isset($bundle['inventory'])) {
            $append($this->fromCountAndEntities(
                type: 'INVENTORY_LOW',
                severity: 'warning',
                count: (int) $bundle['inventory']['low_count'],
                entities: $bundle['inventory']['low_entities'],
                groupedTitle: fn (int $n): string => "{$n} أرصدة منخفضة",
                groupedSubtitle: 'تحت الحد الأدنى',
                groupedHref: DashboardLinks::inventory(['stock_state' => 'low']),
                entityType: 'inventory_balance',
                entityTitle: fn (InventoryBalance $b): string => (string) ($b->item?->name ?? 'صنف'),
                entitySubtitle: fn (InventoryBalance $b): string => (string) ($b->warehouse?->name ?? ''),
                entityHref: fn (InventoryBalance $b): string => DashboardLinks::inventory(['stock_state' => 'low']),
            ));
        }

        if (isset($bundle['meetings'])) {
            $startingSoonIds = $bundle['meetings']['starting_soon_ids'] ?? [];

            $append($this->fromCountAndEntities(
                type: 'MEETING_STARTING_SOON',
                severity: 'warning',
                count: (int) $bundle['meetings']['starting_soon_count'],
                entities: $bundle['meetings']['starting_soon_entities'],
                groupedTitle: fn (int $n): string => "{$n} اجتماعات تبدأ قريبًا",
                groupedSubtitle: 'خلال النافذة القريبة',
                groupedHref: DashboardLinks::meetings(),
                entityType: 'meeting',
                entityTitle: fn (Meeting $m): string => (string) $m->title,
                entitySubtitle: fn (Meeting $m): string => (string) $m->meeting_number,
                entityHref: fn (Meeting $m): string => DashboardLinks::meeting((int) $m->id),
            ));

            $todayEntities = $bundle['meetings']['today_entities']
                ->filter(fn (Meeting $m): bool => ! in_array((int) $m->id, $startingSoonIds, true))
                ->values();
            $todayNotSoonCount = (int) ($bundle['meetings']['today_not_soon_count']
                ?? $todayEntities->count());

            $append($this->fromCountAndEntities(
                type: 'MEETING_TODAY',
                severity: 'info',
                count: $todayNotSoonCount,
                entities: $todayEntities->take(3),
                groupedTitle: fn (int $n): string => "{$n} اجتماعات اليوم",
                groupedSubtitle: 'مجدولة أو جارية اليوم',
                groupedHref: DashboardLinks::meetings(),
                entityType: 'meeting',
                entityTitle: fn (Meeting $m): string => (string) $m->title,
                entitySubtitle: fn (Meeting $m): string => (string) $m->meeting_number,
                entityHref: fn (Meeting $m): string => DashboardLinks::meeting((int) $m->id),
            ));

            $append($this->fromCountAndEntities(
                type: 'MEETING_IN_PROGRESS',
                severity: 'info',
                count: (int) $bundle['meetings']['in_progress_count'],
                entities: $bundle['meetings']['in_progress_entities'],
                groupedTitle: fn (int $n): string => "{$n} اجتماعات جارية",
                groupedSubtitle: 'قيد الانعقاد',
                groupedHref: DashboardLinks::meetings(['status' => 'in_progress']),
                entityType: 'meeting',
                entityTitle: fn (Meeting $m): string => (string) $m->title,
                entitySubtitle: fn (Meeting $m): string => (string) $m->meeting_number,
                entityHref: fn (Meeting $m): string => DashboardLinks::meeting((int) $m->id),
            ));
        }

        return array_slice($items, 0, self::MAX_ITEMS);
    }

    /**
     * @param  Collection<int, mixed>  $entities
     * @param  callable(int): string  $groupedTitle
     * @param  callable(mixed): string  $entityTitle
     * @param  callable(mixed): string|string  $entitySubtitle
     * @param  callable(mixed): string  $entityHref
     * @param  callable(mixed): int|null  $entityId
     * @return list<array<string, mixed>>
     */
    private function fromCountAndEntities(
        string $type,
        string $severity,
        int $count,
        Collection $entities,
        callable $groupedTitle,
        string $groupedSubtitle,
        string $groupedHref,
        string $entityType,
        callable $entityTitle,
        callable|string $entitySubtitle,
        callable $entityHref,
        ?callable $entityId = null,
    ): array {
        if ($count <= 0) {
            return [];
        }

        if ($count > 3) {
            return [[
                'type' => $type,
                'severity' => $severity,
                'title' => $groupedTitle($count),
                'subtitle' => $groupedSubtitle,
                'count' => $count,
                'entity_type' => null,
                'entity_id' => null,
                'href' => $groupedHref,
            ]];
        }

        $items = [];
        foreach ($entities->take($count) as $entity) {
            $subtitle = is_string($entitySubtitle)
                ? $entitySubtitle
                : $entitySubtitle($entity);

            $items[] = [
                'type' => $type,
                'severity' => $severity,
                'title' => $entityTitle($entity),
                'subtitle' => $subtitle,
                'count' => 1,
                'entity_type' => $entityType,
                'entity_id' => $entityId !== null ? $entityId($entity) : (int) $entity->id,
                'href' => $entityHref($entity),
            ];
        }

        return $items;
    }
}
