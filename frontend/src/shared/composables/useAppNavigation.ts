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

export function useAppNavigation(): {
  navGroups: ComputedRef<AppNavGroup[]>
  flatItems: ComputedRef<AppNavItem[]>
  isItemActive: (item: AppNavItem) => boolean
} {
  const route = useRoute()
  const { can } = usePermissions()

  const navGroups = computed<AppNavGroup[]>(() => {
    const groups: AppNavGroup[] = [
      {
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
      },
    ]

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
    if (systemItems.length > 0) {
      groups.push({ key: 'system', labelKey: 'nav.systemAdmin', items: systemItems })
    }

    const organizationItems: AppNavItem[] = []
    if (can('organization_units.view')) {
      organizationItems.push({
        key: 'organization-structure',
        labelKey: 'nav.organization',
        to: '/app/organization',
        icon: Network,
        permission: 'organization_units.view',
        match: 'prefix',
      })
    }
    if (can('employees.view')) {
      organizationItems.push({
        key: 'employees',
        labelKey: 'nav.employees',
        to: '/app/employees',
        icon: IdCard,
        permission: 'employees.view',
        match: 'prefix',
      })
    }
    if (can('contracts.view')) {
      organizationItems.push({
        key: 'contracts',
        labelKey: 'nav.contracts',
        to: '/app/contracts',
        icon: FileText,
        permission: 'contracts.view',
        match: 'prefix',
      })
    }
    if (can('meetings.view')) {
      organizationItems.push({
        key: 'meetings',
        labelKey: 'nav.meetings',
        to: '/app/meetings',
        icon: CalendarDays,
        permission: 'meetings.view',
        match: 'prefix',
      })
    }
    if (can('decisions.view')) {
      organizationItems.push({
        key: 'decisions',
        labelKey: 'nav.decisions',
        to: '/app/decisions',
        icon: Gavel,
        permission: 'decisions.view',
        match: 'prefix',
      })
    }
    if (can('tasks.view')) {
      organizationItems.push({
        key: 'tasks',
        labelKey: 'nav.tasks',
        to: '/app/tasks',
        icon: ListTodo,
        permission: 'tasks.view',
        match: 'prefix',
      })
    }
    if (can('documents.view')) {
      organizationItems.push({
        key: 'documents',
        labelKey: 'nav.documents',
        to: '/app/documents',
        icon: Archive,
        permission: 'documents.view',
        match: 'prefix',
      })
    }
    if (can('warehouses.view')) {
      organizationItems.push({
        key: 'warehouses',
        labelKey: 'nav.warehouses',
        to: '/app/warehouses',
        icon: Warehouse,
        permission: 'warehouses.view',
        match: 'prefix',
      })
    }
    if (can('inventory.view')) {
      organizationItems.push({
        key: 'inventory',
        labelKey: 'nav.inventory',
        to: '/app/inventory',
        icon: Package,
        permission: 'inventory.view',
        match: 'prefix',
      })
    }
    if (can('assets.view')) {
      organizationItems.push({
        key: 'assets',
        labelKey: 'nav.assets',
        to: '/app/assets',
        icon: Boxes,
        permission: 'assets.view',
        match: 'prefix',
      })
      organizationItems.push({
        key: 'my-custodies',
        labelKey: 'nav.myCustodies',
        to: '/app/my-custodies',
        icon: IdCard,
        permission: 'assets.view',
        match: 'exact',
      })
    }
    if (organizationItems.length > 0) {
      groups.push({ key: 'organization', labelKey: 'nav.organization', items: organizationItems })
    }

    return groups
  })

  const flatItems = computed(() => navGroups.value.flatMap((group) => group.items))

  function isItemActive(item: AppNavItem): boolean {
    return isAppNavItemActive(route.path, item)
  }

  return { navGroups, flatItems, isItemActive }
}
