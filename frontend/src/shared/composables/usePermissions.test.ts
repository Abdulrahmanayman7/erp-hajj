import { beforeEach, describe, expect, it, vi } from 'vitest'

const currentUser = vi.hoisted(() => ({
  value: {
    id: 1,
    permissions: ['users.view', 'roles.view'],
    roles: [{ id: 1, code: 'auditor', name: 'Auditor' }],
  },
}))

vi.mock('@/modules/auth/queries/useCurrentUserQuery', () => ({
  useCurrentUserQuery: () => ({ data: currentUser }),
}))

import { usePermissions } from './usePermissions'

describe('usePermissions', () => {
  beforeEach(() => {
    currentUser.value = {
      id: 1,
      permissions: ['users.view', 'roles.view'],
      roles: [{ id: 1, code: 'auditor', name: 'Auditor' }],
    }
  })

  it('can checks a single permission', () => {
    const { can } = usePermissions()
    expect(can('users.view')).toBe(true)
    expect(can('users.create')).toBe(false)
  })

  it('canAny and canAll work', () => {
    const { canAny, canAll } = usePermissions()
    expect(canAny(['users.create', 'roles.view'])).toBe(true)
    expect(canAll(['users.view', 'roles.view'])).toBe(true)
    expect(canAll(['users.view', 'users.create'])).toBe(false)
  })

  it('hasRole checks role codes', () => {
    const { hasRole } = usePermissions()
    expect(hasRole('auditor')).toBe(true)
    expect(hasRole('tenant_owner')).toBe(false)
  })
})
