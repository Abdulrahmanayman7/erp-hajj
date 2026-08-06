/**
 * Shared API client for the ERP Hajj REST API (/api/v1).
 * Implements the standardized response envelope from docs/04-api/API_STANDARDS.md.
 * Uses cookie credentials to support Sanctum SPA authentication later.
 */

export interface ApiSuccess<TData, TMeta = Record<string, unknown>> {
  success: true
  message: string
  data: TData
  meta?: TMeta
}

export interface ApiErrorBody {
  success: false
  message: string
  errors?: Record<string, string[]>
  code?: string
}

export class ApiError extends Error {
  readonly status: number
  readonly code: string | undefined
  readonly errors: Record<string, string[]> | undefined

  constructor(status: number, body: ApiErrorBody | undefined) {
    super(body?.message ?? `Request failed with status ${status}`)
    this.name = 'ApiError'
    this.status = status
    this.code = body?.code
    this.errors = body?.errors
  }
}

const baseUrl: string = import.meta.env.VITE_API_URL ?? ''

export async function apiGet<TData>(path: string): Promise<ApiSuccess<TData>> {
  let response: Response
  try {
    response = await fetch(`${baseUrl}${path}`, {
      method: 'GET',
      headers: { Accept: 'application/json' },
      credentials: 'include',
    })
  } catch {
    throw new ApiError(0, undefined)
  }

  const body = (await response.json().catch(() => undefined)) as
    | ApiSuccess<TData>
    | ApiErrorBody
    | undefined

  if (!response.ok || body === undefined || body.success !== true) {
    throw new ApiError(response.status, body?.success === false ? body : undefined)
  }

  return body
}
