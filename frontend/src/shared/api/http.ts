/**
 * Shared API client for the ERP Hajj REST API (/api/v1).
 * Sanctum SPA: credentials include + CSRF cookie bootstrap.
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

function readXsrfToken(): string | undefined {
  if (typeof document === 'undefined') {
    return undefined
  }

  const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/)
  return match ? decodeURIComponent(match[1]) : undefined
}

let csrfBootstrapped = false

export async function ensureCsrfCookie(): Promise<void> {
  if (csrfBootstrapped && readXsrfToken()) {
    return
  }

  const response = await fetch(`${baseUrl}/sanctum/csrf-cookie`, {
    method: 'GET',
    credentials: 'include',
    headers: { Accept: 'application/json' },
  })

  if (!response.ok) {
    throw new ApiError(response.status, undefined)
  }

  csrfBootstrapped = true
}

export function resetCsrfBootstrap(): void {
  csrfBootstrapped = false
}

async function parseBody(
  response: Response,
): Promise<ApiSuccess<unknown> | ApiErrorBody | undefined> {
  return (await response.json().catch(() => undefined)) as
    | ApiSuccess<unknown>
    | ApiErrorBody
    | undefined
}

async function request<TData>(
  method: string,
  path: string,
  body?: unknown,
  allowCsrfRetry = true,
): Promise<ApiSuccess<TData>> {
  const headers: Record<string, string> = {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  }

  if (body !== undefined) {
    headers['Content-Type'] = 'application/json'
  }

  const xsrf = readXsrfToken()
  if (xsrf) {
    headers['X-XSRF-TOKEN'] = xsrf
  }

  let response: Response
  try {
    response = await fetch(`${baseUrl}${path}`, {
      method,
      headers,
      credentials: 'include',
      body: body === undefined ? undefined : JSON.stringify(body),
    })
  } catch {
    throw new ApiError(0, undefined)
  }

  if (response.status === 419 && allowCsrfRetry) {
    resetCsrfBootstrap()
    await ensureCsrfCookie()
    return request<TData>(method, path, body, false)
  }

  const parsed = await parseBody(response)

  if (!response.ok || parsed === undefined || parsed.success !== true) {
    throw new ApiError(
      response.status,
      parsed?.success === false ? parsed : undefined,
    )
  }

  return parsed as ApiSuccess<TData>
}

export async function apiGet<TData>(path: string): Promise<ApiSuccess<TData>> {
  return request<TData>('GET', path)
}

export async function apiPost<TData>(
  path: string,
  body?: unknown,
  options?: { csrf?: boolean },
): Promise<ApiSuccess<TData>> {
  if (options?.csrf !== false) {
    await ensureCsrfCookie()
  }

  return request<TData>('POST', path, body)
}
