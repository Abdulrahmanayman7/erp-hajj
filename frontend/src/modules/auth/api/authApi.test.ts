import { afterEach, describe, expect, it, vi } from 'vitest'

import { ApiError } from '@/shared/api/http'

import { forgotPassword, login, logout, fetchCurrentUser, resetPassword } from './authApi'

vi.mock('@/shared/api/http', async () => {
  const actual = await vi.importActual<typeof import('@/shared/api/http')>('@/shared/api/http')
  return {
    ...actual,
    apiGet: vi.fn(),
    apiPost: vi.fn(),
    ensureCsrfCookie: vi.fn(),
  }
})

import { apiGet, apiPost } from '@/shared/api/http'

describe('authApi', () => {
  afterEach(() => {
    vi.clearAllMocks()
  })

  it('logs in through the auth endpoint', async () => {
    vi.mocked(apiPost).mockResolvedValue({
      success: true,
      message: 'ok',
      data: {
        id: 1,
        name: 'User',
        email: 'u@example.com',
        status: 'active',
        is_platform_user: false,
        tenant: null,
      },
    })

    const user = await login({ email: 'u@example.com', password: 'Password1' })

    expect(apiPost).toHaveBeenCalledWith('/api/v1/auth/login', {
      email: 'u@example.com',
      password: 'Password1',
    })
    expect(user.email).toBe('u@example.com')
  })

  it('loads the current user', async () => {
    vi.mocked(apiGet).mockResolvedValue({
      success: true,
      message: '',
      data: {
        id: 2,
        name: 'Platform',
        email: 'p@example.com',
        status: 'active',
        is_platform_user: true,
        tenant: null,
      },
    })

    const user = await fetchCurrentUser()
    expect(user.is_platform_user).toBe(true)
  })

  it('propagates ApiError from current user 401', async () => {
    vi.mocked(apiGet).mockRejectedValue(new ApiError(401, {
      success: false,
      message: 'unauth',
      code: 'AUTH_UNAUTHENTICATED',
    }))

    await expect(fetchCurrentUser()).rejects.toMatchObject({ status: 401, code: 'AUTH_UNAUTHENTICATED' })
  })

  it('logs out and resets via forgot/reset helpers', async () => {
    vi.mocked(apiPost).mockResolvedValue({ success: true, message: 'done', data: null })

    await logout()
    await forgotPassword({ email: 'a@b.com' })
    await resetPassword({
      token: 't',
      email: 'a@b.com',
      password: 'Password1',
      password_confirmation: 'Password1',
    })

    expect(apiPost).toHaveBeenCalledWith('/api/v1/auth/logout')
    expect(apiPost).toHaveBeenCalledWith('/api/v1/auth/forgot-password', { email: 'a@b.com' })
    expect(apiPost).toHaveBeenCalledWith('/api/v1/auth/reset-password', expect.any(Object))
  })
})
