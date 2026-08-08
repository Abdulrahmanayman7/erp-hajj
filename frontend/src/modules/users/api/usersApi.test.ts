import { beforeEach, describe, expect, it, vi } from 'vitest'

import * as usersApi from './usersApi'

vi.mock('@/shared/api/http', () => ({
  apiGet: vi.fn(),
  apiPost: vi.fn(),
  apiPut: vi.fn(),
  apiPatch: vi.fn(),
}))

import { apiGet, apiPost } from '@/shared/api/http'

describe('usersApi', () => {
  beforeEach(() => {
    vi.mocked(apiGet).mockReset()
    vi.mocked(apiPost).mockReset()
  })

  it('lists users with query string', async () => {
    vi.mocked(apiGet).mockResolvedValue({
      success: true,
      message: '',
      data: [],
      meta: { current_page: 1, per_page: 15, total: 0, last_page: 1 },
    })

    await usersApi.listUsers({ search: 'ahmad', status: 'active' })

    expect(apiGet).toHaveBeenCalledWith('/api/v1/users?search=ahmad&status=active')
  })

  it('creates a user', async () => {
    vi.mocked(apiPost).mockResolvedValue({
      success: true,
      message: '',
      data: {
        id: 1,
        name: 'New',
        email: 'n@example.com',
        status: 'active',
        roles: [],
        created_at: null,
        updated_at: null,
      },
    })

    const user = await usersApi.createUser({
      name: 'New',
      email: 'n@example.com',
      send_invite: true,
    })

    expect(user.email).toBe('n@example.com')
    expect(apiPost).toHaveBeenCalledWith('/api/v1/users', {
      name: 'New',
      email: 'n@example.com',
      send_invite: true,
    })
  })
})
