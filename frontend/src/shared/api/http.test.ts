import { afterEach, describe, expect, it, vi } from 'vitest'

import { ApiError, apiGet, apiPost, ensureCsrfCookie, resetCsrfBootstrap } from './http'

function mockFetchResponse(status: number, body: unknown, headers?: HeadersInit): void {
  vi.stubGlobal(
    'fetch',
    vi.fn().mockResolvedValue(
      new Response(JSON.stringify(body), {
        status,
        headers: { 'Content-Type': 'application/json', ...headers },
      }),
    ),
  )
}

describe('apiGet', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
    resetCsrfBootstrap()
    document.cookie = 'XSRF-TOKEN=; Max-Age=0; path=/'
  })

  it('returns the envelope when the API responds with success', async () => {
    mockFetchResponse(200, {
      success: true,
      message: 'ERP Hajj API is running',
      data: { version: 'v1' },
    })

    const result = await apiGet<{ version: string }>('/api/v1/health')

    expect(result.success).toBe(true)
    expect(result.message).toBe('ERP Hajj API is running')
    expect(result.data.version).toBe('v1')
  })

  it('throws ApiError with the machine-readable code on error envelopes', async () => {
    mockFetchResponse(422, {
      success: false,
      message: 'Validation failed',
      errors: { name: ['required'] },
      code: 'VALIDATION_ERROR',
    })

    const error = await apiGet('/api/v1/example').catch((caught: unknown) => caught)

    expect(error).toBeInstanceOf(ApiError)
    expect((error as ApiError).status).toBe(422)
    expect((error as ApiError).code).toBe('VALIDATION_ERROR')
    expect((error as ApiError).errors).toEqual({ name: ['required'] })
  })

  it('throws ApiError when the network request fails', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('Network down')))

    const error = await apiGet('/api/v1/health').catch((caught: unknown) => caught)

    expect(error).toBeInstanceOf(ApiError)
    expect((error as ApiError).status).toBe(0)
  })
})

describe('CSRF and apiPost', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
    resetCsrfBootstrap()
    document.cookie = 'XSRF-TOKEN=; Max-Age=0; path=/'
  })

  it('bootstraps the CSRF cookie before posting', async () => {
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce(new Response('', { status: 204 }))
      .mockResolvedValueOnce(
        new Response(JSON.stringify({ success: true, message: 'ok', data: { id: 1 } }), {
          status: 200,
          headers: { 'Content-Type': 'application/json' },
        }),
      )

    vi.stubGlobal('fetch', fetchMock)
    document.cookie = 'XSRF-TOKEN=test-token; path=/'

    await ensureCsrfCookie()
    await apiPost('/api/v1/auth/login', { email: 'a@b.com', password: 'x' }, { csrf: false })

    expect(fetchMock).toHaveBeenCalled()
    expect(String(fetchMock.mock.calls[0][0])).toContain('/sanctum/csrf-cookie')
  })

  it('retries once on 419 after refreshing CSRF', async () => {
    document.cookie = 'XSRF-TOKEN=old; path=/'

    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce(new Response('', { status: 204 }))
      .mockResolvedValueOnce(
        new Response(JSON.stringify({ success: false, message: 'CSRF', code: 'CSRF' }), {
          status: 419,
          headers: { 'Content-Type': 'application/json' },
        }),
      )
      .mockResolvedValueOnce(new Response('', { status: 204 }))
      .mockResolvedValueOnce(
        new Response(JSON.stringify({ success: true, message: 'ok', data: null }), {
          status: 200,
          headers: { 'Content-Type': 'application/json' },
        }),
      )

    vi.stubGlobal('fetch', fetchMock)

    const result = await apiPost('/api/v1/auth/logout')

    expect(result.success).toBe(true)
    expect(fetchMock.mock.calls.length).toBeGreaterThanOrEqual(3)
  })
})
