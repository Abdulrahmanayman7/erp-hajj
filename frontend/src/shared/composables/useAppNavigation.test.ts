import { describe, expect, it } from 'vitest'

import { isAppNavItemActive, type AppNavItem } from './useAppNavigation'
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
