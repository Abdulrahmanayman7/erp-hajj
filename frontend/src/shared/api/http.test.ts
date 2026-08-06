import { afterEach, describe, expect, it, vi } from 'vitest'

import { ApiError, apiGet } from './http'

function mockFetchResponse(status: number, body: unknown): void {
  vi.stubGlobal(
    'fetch',
    vi.fn().mockResolvedValue(
      new Response(JSON.stringify(body), {
        status,
        headers: { 'Content-Type': 'application/json' },
      }),
    ),
  )
}

describe('apiGet', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
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
