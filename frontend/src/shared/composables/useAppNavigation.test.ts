import { describe, expect, it } from 'vitest'

import {
  buildTenantNavGroups,
  filterNavGroups,
  isAppNavItemActive,
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
  'governance',
  'inventory',
  'transport',
  'assets',
  'accommodation',
] as const

const EXPECTED_LINK_KEYS = [
  'dashboard',
  'users',
  'roles',
  'audit',
  'settings',
  'organization-structure',
  'employees',
  'seasons',
  'pilgrims',
  'season-groups',
  'season-programs',
  'season-families',
  'contracts',
  'meetings',
  'decisions',
  'tasks',
  'documents',
  'warehouses',
  'inventory',
  'transport-vehicles',
  'transport-drivers',
  'transport-season-fleet',
  'transport-points',
  'transport-movements',
  'transport-breakdowns',
  'assets',
  'my-custodies',
  'accommodation',
] as const

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

  it('keeps nine functional groups in the approved order when all permissions are granted', () => {
    const groups = buildTenantNavGroups(allowAll)
    expect(groups.map((group) => group.key)).toEqual([...EXPECTED_GROUP_ORDER])
  })

  it('exposes exactly 28 sidebar links without duplication', () => {
    const groups = buildTenantNavGroups(allowAll)
    const keys = groups.flatMap((group) => group.items.map((item) => item.key))
    expect(keys).toEqual([...EXPECTED_LINK_KEYS])
    expect(new Set(keys).size).toBe(28)
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
})
