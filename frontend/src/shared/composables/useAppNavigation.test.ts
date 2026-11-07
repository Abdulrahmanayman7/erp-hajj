import { describe, expect, it } from 'vitest'

import {
  buildTenantNavGroups,
  filterNavGroups,
  isAppNavItemActive,
  resolveActiveMultiItemGroupKey,
  type AppNavGroup,
  type AppNavItem,
} from './useAppNavigation'
import { Home, ListTodo } from 'lucide-vue-next'

const home: AppNavItem = {
  key: 'dashboard',
  labelKey: 'nav.dashboard',
  to: '/app',
  icon: Home,
  match: 'exact',
}

const tasks: AppNavItem = {
  key: 'tasks',
  labelKey: 'nav.tasks',
  to: '/app/tasks',
  icon: ListTodo,
  match: 'prefix',
  permission: 'tasks.view',
}

const EXPECTED_GROUP_ORDER = [
  'home',
  'system',
  'hr',
  'seasons-pilgrims',
  'accommodation',
  'transport',
  'procurement',
  'suppliers-contracts',
  'inventory',
  'assets',
  'governance',
] as const

const EXPECTED_GROUP_MEMBERSHIP: Record<(typeof EXPECTED_GROUP_ORDER)[number], readonly string[]> = {
  home: ['dashboard'],
  system: ['users', 'roles', 'audit', 'settings'],
  hr: ['organization-structure', 'employees'],
  'seasons-pilgrims': ['seasons', 'pilgrims', 'season-groups', 'season-programs', 'season-families'],
  accommodation: ['accommodation'],
  transport: [
    'transport-vehicles',
    'transport-drivers',
    'transport-season-fleet',
    'transport-points',
    'transport-movements',
    'transport-breakdowns',
  ],
  procurement: [
    'purchase-requests',
    'purchase-requests-approval-inbox',
    'purchase-demand-groups',
    'rfqs',
    'purchase-orders',
    'goods-receipts',
    'purchase-control-reports',
    'purchase-approval-settings',
  ],
  'suppliers-contracts': ['suppliers', 'supplier-evaluations', 'contracts'],
  inventory: ['warehouses', 'inventory'],
  assets: ['assets', 'my-custodies'],
  governance: ['meetings', 'decisions', 'tasks', 'risks', 'complaints', 'documents'],
}

const EXPECTED_LINK_KEYS = EXPECTED_GROUP_ORDER.flatMap((key) => EXPECTED_GROUP_MEMBERSHIP[key])

describe('isAppNavItemActive', () => {
  it('matches exact routes only for exact items', () => {
    expect(isAppNavItemActive('/app', home)).toBe(true)
    expect(isAppNavItemActive('/app/', home)).toBe(true)
    expect(isAppNavItemActive('/app/tasks', home)).toBe(false)
  })

  it('matches prefix routes for prefix items', () => {
    expect(isAppNavItemActive('/app/tasks', tasks)).toBe(true)
    expect(isAppNavItemActive('/app/tasks/12', tasks)).toBe(true)
    expect(isAppNavItemActive('/app/meetings', tasks)).toBe(false)
  })

  it('keeps approval-inbox exact while purchase-requests remains prefix', () => {
    const groups = buildTenantNavGroups(() => true)
    const byKey = Object.fromEntries(
      groups.flatMap((group) => group.items).map((item) => [item.key, item]),
    )
    const inbox = byKey['purchase-requests-approval-inbox']!
    const requests = byKey['purchase-requests']!

    expect(isAppNavItemActive('/app/purchase-requests/approval-inbox', inbox)).toBe(true)
    expect(isAppNavItemActive('/app/purchase-requests/approval-inbox', requests)).toBe(true)
    expect(isAppNavItemActive('/app/purchase-requests/99', inbox)).toBe(false)
    expect(isAppNavItemActive('/app/purchase-requests/99', requests)).toBe(true)
  })
})

describe('filterNavGroups', () => {
  const groups: AppNavGroup[] = [
    {
      key: 'home',
      labelKey: 'nav.home',
      items: [home],
    },
    {
      key: 'work',
      labelKey: 'nav.tasks',
      items: [tasks],
    },
  ]

  const labels: Record<string, string> = {
    'nav.home': 'الرئيسية',
    'nav.dashboard': 'لوحة التحكم',
    'nav.tasks': 'المهام',
  }

  it('keeps only visible keys then filters by local query', () => {
    const visible = filterNavGroups(groups, {
      visibleKeys: new Set(['tasks']),
      query: '',
      labelOf: (key) => labels[key] ?? key,
    })
    expect(visible.map((group) => group.key)).toEqual(['work'])
    expect(visible[0]?.items.map((item) => item.key)).toEqual(['tasks'])

    const queried = filterNavGroups(groups, {
      query: 'مهام',
      labelOf: (key) => labels[key] ?? key,
    })
    expect(queried.map((group) => group.key)).toEqual(['work'])

    const empty = filterNavGroups(groups, {
      query: 'عقود',
      labelOf: (key) => labels[key] ?? key,
    })
    expect(empty).toEqual([])
  })
})

describe('buildTenantNavGroups', () => {
  const allowAll = (): boolean => true

  it('keeps eleven functional groups in the approved order when all permissions are granted', () => {
    const groups = buildTenantNavGroups(allowAll)
    expect(groups.map((group) => group.key)).toEqual([...EXPECTED_GROUP_ORDER])
    expect(groups).toHaveLength(11)
  })

  it('exposes exactly 40 sidebar links without duplication', () => {
    const groups = buildTenantNavGroups(allowAll)
    const keys = groups.flatMap((group) => group.items.map((item) => item.key))
    expect(keys).toEqual([...EXPECTED_LINK_KEYS])
    expect(new Set(keys).size).toBe(40)
  })

  it('keeps exact membership and order inside every approved group', () => {
    const groups = buildTenantNavGroups(allowAll)
    for (const group of groups) {
      const expected = EXPECTED_GROUP_MEMBERSHIP[group.key as (typeof EXPECTED_GROUP_ORDER)[number]]
      expect(group.items.map((item) => item.key)).toEqual([...expected])
    }
  })

  it('preserves routes for every link', () => {
    const byKey = Object.fromEntries(
      buildTenantNavGroups(allowAll)
        .flatMap((group) => group.items)
        .map((item) => [item.key, item.to]),
    )
    expect(byKey.dashboard).toBe('/app')
    expect(byKey.users).toBe('/app/users')
    expect(byKey.roles).toBe('/app/roles')
    expect(byKey.audit).toBe('/app/audit')
    expect(byKey.settings).toBe('/app/settings')
    expect(byKey['organization-structure']).toBe('/app/organization')
    expect(byKey.employees).toBe('/app/employees')
    expect(byKey.seasons).toBe('/app/seasons')
    expect(byKey.pilgrims).toBe('/app/pilgrims')
    expect(byKey['season-groups']).toBe('/app/season-groups')
    expect(byKey['season-programs']).toBe('/app/season-programs')
    expect(byKey['season-families']).toBe('/app/season-families')
    expect(byKey.contracts).toBe('/app/contracts')
    expect(byKey.suppliers).toBe('/app/suppliers')
    expect(byKey['supplier-evaluations']).toBe('/app/supplier-evaluations')
    expect(byKey['purchase-requests']).toBe('/app/purchase-requests')
    expect(byKey.rfqs).toBe('/app/rfqs')
    expect(byKey['purchase-orders']).toBe('/app/purchase-orders')
    expect(byKey['goods-receipts']).toBe('/app/goods-receipts')
    expect(byKey['purchase-requests-approval-inbox']).toBe('/app/purchase-requests/approval-inbox')
    expect(byKey['purchase-demand-groups']).toBe('/app/purchase-demand-groups')
    expect(byKey['purchase-control-reports']).toBe('/app/purchase-control-reports')
    expect(byKey['purchase-approval-settings']).toBe('/app/purchase-approval-settings')
    expect(byKey.meetings).toBe('/app/meetings')
    expect(byKey.decisions).toBe('/app/decisions')
    expect(byKey.tasks).toBe('/app/tasks')
    expect(byKey.documents).toBe('/app/documents')
    expect(byKey.warehouses).toBe('/app/warehouses')
    expect(byKey.inventory).toBe('/app/inventory')
    expect(byKey['transport-vehicles']).toBe('/app/transport-vehicles')
    expect(byKey['transport-drivers']).toBe('/app/transport-drivers')
    expect(byKey['transport-season-fleet']).toBe('/app/transport-season-fleet')
    expect(byKey['transport-points']).toBe('/app/transport-points')
    expect(byKey['transport-movements']).toBe('/app/transport-movements')
    expect(byKey['transport-breakdowns']).toBe('/app/transport-breakdowns')
    expect(byKey.assets).toBe('/app/assets')
    expect(byKey['my-custodies']).toBe('/app/my-custodies')
    expect(byKey.accommodation).toBe('/app/accommodation')
  })

  it('omits empty groups and shows only seasons-pilgrims when those permissions alone are granted', () => {
    const allowed = new Set([
      'seasons.view',
      'pilgrims.view',
      'season_pilgrim_groups.view',
      'season_programs.view',
      'season_family_groups.view',
    ])
    const groups = buildTenantNavGroups((permission) => allowed.has(permission))
    expect(groups.map((group) => group.key)).toEqual(['home', 'seasons-pilgrims'])
    expect(groups.find((group) => group.key === 'seasons-pilgrims')?.items.map((item) => item.key)).toEqual([
      'seasons',
      'pilgrims',
      'season-groups',
      'season-programs',
      'season-families',
    ])
  })

  it('shows only transport links when transportation.view alone is granted', () => {
    const groups = buildTenantNavGroups((permission) => permission === 'transportation.view')
    expect(groups.map((group) => group.key)).toEqual(['home', 'transport'])
    expect(groups[1]?.items).toHaveLength(6)
    expect(groups[1]?.items.every((item) => item.permission === 'transportation.view')).toBe(true)
  })

  it('shows accommodation when any accommodation permission is granted', () => {
    const groups = buildTenantNavGroups((permission) => permission === 'accommodation.draft')
    expect(groups.map((group) => group.key)).toEqual(['home', 'accommodation'])
    expect(groups[1]?.items.map((item) => item.key)).toEqual(['accommodation'])
    expect(groups[1]?.items[0]?.to).toBe('/app/accommodation')
  })

  it('keeps home dashboard without a permission gate (existing behavior)', () => {
    const groups = buildTenantNavGroups(() => false)
    expect(groups.map((group) => group.key)).toEqual(['home'])
    expect(groups[0]?.items.map((item) => item.key)).toEqual(['dashboard'])
  })

  it('places procurement and suppliers-contracts as separate permission-gated groups', () => {
    const procurementOnly = buildTenantNavGroups(
      (permission) => permission === 'purchase_requests.view' || permission === 'rfq.view',
    )
    expect(procurementOnly.map((group) => group.key)).toEqual(['home', 'procurement'])
    expect(procurementOnly[1]?.items.map((item) => item.key)).toEqual([
      'purchase-requests',
      'purchase-demand-groups',
      'rfqs',
    ])

    const suppliersOnly = buildTenantNavGroups(
      (permission) => permission === 'suppliers.view' || permission === 'contracts.view',
    )
    expect(suppliersOnly.map((group) => group.key)).toEqual(['home', 'suppliers-contracts'])
    expect(suppliersOnly[1]?.items.map((item) => item.key)).toEqual(['suppliers', 'contracts'])
  })
})

describe('resolveActiveMultiItemGroupKey', () => {
  const groups = buildTenantNavGroups(() => true)

  it('returns null for single-item destinations (home / accommodation)', () => {
    expect(resolveActiveMultiItemGroupKey(groups, '/app')).toBeNull()
    expect(resolveActiveMultiItemGroupKey(groups, '/app/accommodation')).toBeNull()
    expect(resolveActiveMultiItemGroupKey(groups, '/app/accommodation/drafts')).toBeNull()
  })

  it('auto-selects the multi-item group for deep links', () => {
    expect(resolveActiveMultiItemGroupKey(groups, '/app/tasks/12')).toBe('governance')
    expect(resolveActiveMultiItemGroupKey(groups, '/app/rfqs/42')).toBe('procurement')
    expect(resolveActiveMultiItemGroupKey(groups, '/app/purchase-requests/approval-inbox')).toBe(
      'procurement',
    )
    expect(resolveActiveMultiItemGroupKey(groups, '/app/transport-vehicles')).toBe('transport')
    expect(resolveActiveMultiItemGroupKey(groups, '/app/suppliers/9')).toBe('suppliers-contracts')
  })

  it('supports exclusive accordion selection semantics', () => {
    const openA = resolveActiveMultiItemGroupKey(groups, '/app/users')
    const openB = resolveActiveMultiItemGroupKey(groups, '/app/meetings')
    expect(openA).toBe('system')
    expect(openB).toBe('governance')
    expect(openA).not.toBe(openB)
  })
})
