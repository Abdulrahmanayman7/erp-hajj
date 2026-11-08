<?php

namespace App\Modules\OperationalKpis\Support;

use App\Models\User;
use InvalidArgumentException;

/**
 * Frozen S09-D01 catalog of the 24 KPI ids → calculator group, domain
 * permission (D08), Arabic label and allow-listed drill-down path (D11=A).
 * Drill-down paths are static existing frontend list routes — never built
 * from user input.
 */
final class OperationalKpiCatalog
{
    public const GROUP_TRANSPORT = 'transport';

    public const GROUP_ACCOMMODATION = 'accommodation';

    public const GROUP_PROCUREMENT = 'procurement';

    public const GROUP_SEASON_PROJECTS = 'season_projects';

    public const GROUP_RISKS_COMPLAINTS = 'risks_complaints';

    /**
     * Ordered as rendered.
     *
     * @var array<string, array{group: string, domain: string, permission: string, label: string, href: string}>
     */
    private const KPIS = [
        'T01' => ['group' => self::GROUP_TRANSPORT, 'domain' => 'transport', 'permission' => 'transportation.view', 'label' => 'الحركات المجدولة', 'href' => '/app/transport-movements'],
        'T02' => ['group' => self::GROUP_TRANSPORT, 'domain' => 'transport', 'permission' => 'transportation.view', 'label' => 'الحركات المكتملة', 'href' => '/app/transport-movements'],
        'T08' => ['group' => self::GROUP_TRANSPORT, 'domain' => 'transport', 'permission' => 'transportation.view', 'label' => 'أعطال المركبات', 'href' => '/app/transport-breakdowns'],

        'A02' => ['group' => self::GROUP_ACCOMMODATION, 'domain' => 'accommodation', 'permission' => 'accommodation.view', 'label' => 'الطاقة المحجوزة (تخصيص)', 'href' => '/app/accommodation/allocation'],
        'A03' => ['group' => self::GROUP_ACCOMMODATION, 'domain' => 'accommodation', 'permission' => 'accommodation.view', 'label' => 'الإشغال الفعلي', 'href' => '/app/accommodation/allocation'],

        'P01' => ['group' => self::GROUP_PROCUREMENT, 'domain' => 'purchase_requests', 'permission' => 'purchase_requests.view', 'label' => 'طلبات الشراء', 'href' => '/app/purchase-requests'],
        'P02' => ['group' => self::GROUP_PROCUREMENT, 'domain' => 'purchase_requests', 'permission' => 'purchase_requests.view', 'label' => 'طلبات معتمدة', 'href' => '/app/purchase-requests'],
        'P03' => ['group' => self::GROUP_PROCUREMENT, 'domain' => 'rfq', 'permission' => 'rfq.view', 'label' => 'طلبات عروض صادرة', 'href' => '/app/rfqs'],
        'P04' => ['group' => self::GROUP_PROCUREMENT, 'domain' => 'purchase_orders', 'permission' => 'purchase_orders.view', 'label' => 'أوامر شراء منشأة', 'href' => '/app/purchase-orders'],
        'P05' => ['group' => self::GROUP_PROCUREMENT, 'domain' => 'purchase_orders', 'permission' => 'purchase_orders.view', 'label' => 'أوامر صادرة', 'href' => '/app/purchase-orders'],
        'P06' => ['group' => self::GROUP_PROCUREMENT, 'domain' => 'purchase_orders', 'permission' => 'purchase_orders.view', 'label' => 'أوامر مستوفاة', 'href' => '/app/purchase-orders'],
        'P07' => ['group' => self::GROUP_PROCUREMENT, 'domain' => 'purchase_orders', 'permission' => 'purchase_orders.view', 'label' => 'الكميات المستلمة', 'href' => '/app/purchase-orders'],
        'P08' => ['group' => self::GROUP_PROCUREMENT, 'domain' => 'purchase_orders', 'permission' => 'purchase_orders.view', 'label' => 'استلامات معلّقة', 'href' => '/app/purchase-orders'],

        'J01' => ['group' => self::GROUP_SEASON_PROJECTS, 'domain' => 'season_projects', 'permission' => 'season_projects.view', 'label' => 'مشاريع الموسم', 'href' => '/app/season-projects'],
        'J02' => ['group' => self::GROUP_SEASON_PROJECTS, 'domain' => 'season_projects', 'permission' => 'season_projects.view', 'label' => 'مشاريع نشطة', 'href' => '/app/season-projects'],
        'J03' => ['group' => self::GROUP_SEASON_PROJECTS, 'domain' => 'season_projects', 'permission' => 'season_projects.view', 'label' => 'مشاريع مكتملة', 'href' => '/app/season-projects'],
        'J07' => ['group' => self::GROUP_SEASON_PROJECTS, 'domain' => 'season_projects', 'permission' => 'season_projects.view', 'label' => 'التقدم الفعلي', 'href' => '/app/season-projects'],
        'J09' => ['group' => self::GROUP_SEASON_PROJECTS, 'domain' => 'season_projects', 'permission' => 'season_projects.view', 'label' => 'معدل إكمال المشاريع', 'href' => '/app/season-projects'],

        'R01' => ['group' => self::GROUP_RISKS_COMPLAINTS, 'domain' => 'risks', 'permission' => 'risks.view', 'label' => 'مخاطر مفتوحة', 'href' => '/app/risks'],
        'R02' => ['group' => self::GROUP_RISKS_COMPLAINTS, 'domain' => 'risks', 'permission' => 'risks.view', 'label' => 'مخاطر عالية/حرجة', 'href' => '/app/risks'],
        'R03' => ['group' => self::GROUP_RISKS_COMPLAINTS, 'domain' => 'risks', 'permission' => 'risks.view', 'label' => 'مخاطر مُخفَّفة', 'href' => '/app/risks'],
        'R04' => ['group' => self::GROUP_RISKS_COMPLAINTS, 'domain' => 'complaints', 'permission' => 'complaints.view', 'label' => 'شكاوى مفتوحة', 'href' => '/app/complaints'],
        'R05' => ['group' => self::GROUP_RISKS_COMPLAINTS, 'domain' => 'complaints', 'permission' => 'complaints.view', 'label' => 'شكاوى محلولة', 'href' => '/app/complaints'],
        'R09' => ['group' => self::GROUP_RISKS_COMPLAINTS, 'domain' => 'complaints', 'permission' => 'complaints.view', 'label' => 'شكاوى مُصعَّدة', 'href' => '/app/complaints'],
    ];

    /**
     * @return list<string>
     */
    public static function ids(): array
    {
        return array_keys(self::KPIS);
    }

    /**
     * @return array{group: string, domain: string, permission: string, label: string, href: string}
     */
    public static function definition(string $id): array
    {
        return self::KPIS[$id] ?? throw new InvalidArgumentException("Unknown operational KPI id [{$id}].");
    }

    /**
     * @return list<string>
     */
    public static function groups(): array
    {
        return [
            self::GROUP_TRANSPORT,
            self::GROUP_ACCOMMODATION,
            self::GROUP_PROCUREMENT,
            self::GROUP_SEASON_PROJECTS,
            self::GROUP_RISKS_COMPLAINTS,
        ];
    }

    /**
     * KPI ids of a calculator group the actor may see (per-KPI domain permission).
     *
     * @return list<string>
     */
    public static function authorizedIdsForGroup(User $actor, string $group): array
    {
        $ids = [];

        foreach (self::KPIS as $id => $meta) {
            if ($meta['group'] === $group && $actor->hasPermission($meta['permission'])) {
                $ids[] = $id;
            }
        }

        return $ids;
    }
}
