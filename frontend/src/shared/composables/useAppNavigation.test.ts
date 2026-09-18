import { describe, expect, it } from 'vitest'

import { isAppNavItemActive, filterNavGroups, type AppNavGroup, type AppNavItem } from './useAppNavigation'
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
