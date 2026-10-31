import { computed, type ComputedRef } from 'vue'
import { useRoute } from 'vue-router'
import {
  Archive,
  Boxes,
  CalendarDays,
  FileText,
  Gavel,
  Home,
  IdCard,
  ListTodo,
  Network,
  Package,
  ScrollText,
  Settings,
  Shield,
  Users,
  Warehouse,
  Tent,
  CalendarRange,
  UserRound,
  Layers3,
  Shapes,
  HeartHandshake,
  Bus,
  UserCog,
  Truck,
  MapPinned,
  Route,
  TriangleAlert,
  type LucideIcon,
} from 'lucide-vue-next'

import { usePermissions } from '@/shared/composables/usePermissions'

export interface AppNavItem {
  key: string
  labelKey: string
  to: string
  icon: LucideIcon
  permission?: string
  match?: 'exact' | 'prefix'
}

export interface AppNavGroup {
  key: string
  labelKey: string
  items: AppNavItem[]
}

export function isAppNavItemActive(path: string, item: AppNavItem): boolean {
  if (item.match === 'exact') {
    return path === item.to || path === `${item.to}/`
  }
  return path === item.to || path.startsWith(`${item.to}/`)
}

/** Local menu filter only — never searches entities across the system. */
export function filterNavGroups(
  groups: AppNavGroup[],
  options: {
    visibleKeys?: Set<string> | null
    query?: string
    labelOf: (key: string) => string
  },
): AppNavGroup[] {
  const query = (options.query ?? '').trim()
  const keys = options.visibleKeys

  return groups
    .map((group) => ({
      ...group,
      items: group.items.filter((item) => {
        if (keys && !keys.has(item.key)) return false
        if (!query) return true
        const haystack = `${options.labelOf(item.labelKey)} ${options.labelOf(group.labelKey)}`
        return haystack.includes(query)
      }),
    }))
    .filter((group) => group.items.length > 0)
}

function pushGroup(groups: AppNavGroup[], group: AppNavGroup): void {
  if (group.items.length > 0) {
    groups.push(group)
  }
}

/**
 * Builds tenant-app sidebar groups. Permission checks match prior behavior;
 * routes and permission names are unchanged. Empty groups are omitted.
 */
export function buildTenantNavGroups(can: (permission: string) => boolean): AppNavGroup[] {
  const groups: AppNavGroup[] = []

  pushGroup(groups, {
    key: 'home',
    labelKey: 'nav.home',
    items: [
      {
        key: 'dashboard',
        labelKey: 'nav.dashboard',
        to: '/app',
        icon: Home,
        match: 'exact',
      },
    ],
  })

  const systemItems: AppNavItem[] = []
  if (can('users.view')) {
    systemItems.push({
      key: 'users',
      labelKey: 'nav.users',
      to: '/app/users',
      icon: Users,
      permission: 'users.view',
      match: 'prefix',
    })
  }
  if (can('roles.view')) {
    systemItems.push({
      key: 'roles',
      labelKey: 'nav.rolesShort',
      to: '/app/roles',
      icon: Shield,
      permission: 'roles.view',
      match: 'prefix',
    })
  }
  if (can('audit_logs.view')) {
    systemItems.push({
      key: 'audit',
      labelKey: 'nav.audit',
      to: '/app/audit',
      icon: ScrollText,
      permission: 'audit_logs.view',
      match: 'prefix',
    })
  }
  if (can('tenant_settings.view')) {
    systemItems.push({
      key: 'settings',
      labelKey: 'nav.settings',
      to: '/app/settings',
      icon: Settings,
      permission: 'tenant_settings.view',
      match: 'prefix',
    })
  }
  pushGroup(groups, { key: 'system', labelKey: 'nav.systemAdmin', items: systemItems })

  const hrItems: AppNavItem[] = []
  if (can('organization_units.view')) {
    hrItems.push({
      key: 'organization-structure',
      labelKey: 'nav.organization',
      to: '/app/organization',
      icon: Network,
      permission: 'organization_units.view',
      match: 'prefix',
    })
  }
  if (can('employees.view')) {
    hrItems.push({
      key: 'employees',
      labelKey: 'nav.employees',
      to: '/app/employees',
      icon: IdCard,
      permission: 'employees.view',
      match: 'prefix',
    })
  }
  pushGroup(groups, { key: 'hr', labelKey: 'nav.hrPeople', items: hrItems })

  const seasonItems: AppNavItem[] = []
  if (can('seasons.view')) {
    seasonItems.push({
      key: 'seasons',
      labelKey: 'nav.seasons',
      to: '/app/seasons',
      icon: CalendarRange,
      permission: 'seasons.view',
      match: 'prefix',
    })
  }
  if (can('pilgrims.view')) {
    seasonItems.push({
      key: 'pilgrims',
      labelKey: 'nav.pilgrims',
      to: '/app/pilgrims',
      icon: UserRound,
      permission: 'pilgrims.view',
      match: 'prefix',
    })
  }
  if (can('season_pilgrim_groups.view')) {
    seasonItems.push({
      key: 'season-groups',
      labelKey: 'nav.seasonGroups',
      to: '/app/season-groups',
      icon: Layers3,
      permission: 'season_pilgrim_groups.view',
      match: 'prefix',
    })
  }
  if (can('season_programs.view')) {
    seasonItems.push({
      key: 'season-programs',
      labelKey: 'nav.seasonPrograms',
      to: '/app/season-programs',
      icon: Shapes,
      permission: 'season_programs.view',
      match: 'prefix',
    })
  }
  if (can('season_family_groups.view')) {
    seasonItems.push({
      key: 'season-families',
      labelKey: 'nav.seasonFamilies',
      to: '/app/season-families',
      icon: HeartHandshake,
      permission: 'season_family_groups.view',
      match: 'prefix',
    })
  }
  pushGroup(groups, { key: 'seasons-pilgrims', labelKey: 'nav.seasonsPilgrims', items: seasonItems })

  const governanceItems: AppNavItem[] = []
  if (can('contracts.view')) {
    governanceItems.push({
      key: 'contracts',
      labelKey: 'nav.contracts',
      to: '/app/contracts',
      icon: FileText,
      permission: 'contracts.view',
      match: 'prefix',
    })
  }
  if (can('meetings.view')) {
    governanceItems.push({
      key: 'meetings',
      labelKey: 'nav.meetings',
      to: '/app/meetings',
      icon: CalendarDays,
      permission: 'meetings.view',
      match: 'prefix',
    })
  }
  if (can('decisions.view')) {
    governanceItems.push({
      key: 'decisions',
      labelKey: 'nav.decisions',
      to: '/app/decisions',
      icon: Gavel,
      permission: 'decisions.view',
      match: 'prefix',
    })
  }
  if (can('tasks.view')) {
    governanceItems.push({
      key: 'tasks',
      labelKey: 'nav.tasks',
      to: '/app/tasks',
      icon: ListTodo,
      permission: 'tasks.view',
      match: 'prefix',
    })
  }
  if (can('documents.view')) {
    governanceItems.push({
      key: 'documents',
      labelKey: 'nav.documents',
      to: '/app/documents',
      icon: Archive,
      permission: 'documents.view',
      match: 'prefix',
    })
  }
  pushGroup(groups, { key: 'governance', labelKey: 'nav.governance', items: governanceItems })

  const inventoryItems: AppNavItem[] = []
  if (can('warehouses.view')) {
    inventoryItems.push({
      key: 'warehouses',
      labelKey: 'nav.warehouses',
      to: '/app/warehouses',
      icon: Warehouse,
      permission: 'warehouses.view',
      match: 'prefix',
    })
  }
  if (can('inventory.view')) {
    inventoryItems.push({
      key: 'inventory',
      labelKey: 'nav.inventory',
      to: '/app/inventory',
      icon: Package,
      permission: 'inventory.view',
      match: 'prefix',
    })
  }
  pushGroup(groups, { key: 'inventory', labelKey: 'nav.inventoryWarehouses', items: inventoryItems })

  const transportItems: AppNavItem[] = []
  if (can('transportation.view')) {
    transportItems.push(
      {
        key: 'transport-vehicles',
        labelKey: 'nav.transportVehicles',
        to: '/app/transport-vehicles',
        icon: Bus,
        permission: 'transportation.view',
        match: 'prefix',
      },
      {
        key: 'transport-drivers',
        labelKey: 'nav.transportDrivers',
        to: '/app/transport-drivers',
        icon: UserCog,
        permission: 'transportation.view',
        match: 'prefix',
      },
      {
        key: 'transport-season-fleet',
        labelKey: 'nav.transportSeasonFleet',
        to: '/app/transport-season-fleet',
        icon: Truck,
        permission: 'transportation.view',
        match: 'prefix',
      },
      {
        key: 'transport-points',
        labelKey: 'nav.transportPoints',
        to: '/app/transport-points',
        icon: MapPinned,
        permission: 'transportation.view',
        match: 'prefix',
      },
      {
        key: 'transport-movements',
        labelKey: 'nav.transportMovements',
        to: '/app/transport-movements',
        icon: Route,
        permission: 'transportation.view',
        match: 'prefix',
      },
      {
        key: 'transport-breakdowns',
        labelKey: 'nav.transportBreakdowns',
        to: '/app/transport-breakdowns',
        icon: TriangleAlert,
        permission: 'transportation.view',
        match: 'prefix',
      },
    )
  }
  pushGroup(groups, { key: 'transport', labelKey: 'nav.transportFleet', items: transportItems })

  const assetsItems: AppNavItem[] = []
  if (can('assets.view')) {
    assetsItems.push(
      {
        key: 'assets',
        labelKey: 'nav.assets',
        to: '/app/assets',
        icon: Boxes,
        permission: 'assets.view',
        match: 'prefix',
      },
      {
        key: 'my-custodies',
        labelKey: 'nav.myCustodies',
        to: '/app/my-custodies',
        icon: IdCard,
        permission: 'assets.view',
        match: 'exact',
      },
    )
  }
  pushGroup(groups, { key: 'assets', labelKey: 'nav.assetsCustodies', items: assetsItems })

  const accommodationItems: AppNavItem[] = []
  if (
    can('accommodation.view') ||
    can('accommodation.manage_facilities') ||
    can('accommodation.manage_season_allocation') ||
    can('accommodation.draft') ||
    can('accommodation.confirm') ||
    can('accommodation.manage_scopes') ||
    can('accommodation.arrive') ||
    can('accommodation.check_in') ||
    can('accommodation.check_out') ||
    can('accommodation.relocate') ||
    can('accommodation.cancel_assignment')
  ) {
    accommodationItems.push({
      key: 'accommodation',
      labelKey: 'nav.accommodation',
      to: '/app/accommodation',
      icon: Tent,
      match: 'prefix',
    })
  }
  pushGroup(groups, {
    key: 'accommodation',
    labelKey: 'nav.accommodationGroup',
    items: accommodationItems,
  })

  return groups
}

export function useAppNavigation(): {
  navGroups: ComputedRef<AppNavGroup[]>
  flatItems: ComputedRef<AppNavItem[]>
  isItemActive: (item: AppNavItem) => boolean
} {
  const route = useRoute()
  const { can } = usePermissions()

  const navGroups = computed<AppNavGroup[]>(() => buildTenantNavGroups(can))

  const flatItems = computed(() => navGroups.value.flatMap((group) => group.items))

  function isItemActive(item: AppNavItem): boolean {
    return isAppNavItemActive(route.path, item)
  }

  return { navGroups, flatItems, isItemActive }
}
