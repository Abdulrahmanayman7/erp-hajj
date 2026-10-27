import { assertSession, sessionFetch, sessionGeneration } from './sessionLifetime'

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
    super(body?.message ?? (status === 0 ? 'تعذر الاتصال بالخادم. تحقق من الاتصال وحاول مجددًا.' : 'تعذر إكمال الطلب. حاول مجددًا.'))
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

  const response = await sessionFetch(`${baseUrl}/sanctum/csrf-cookie`, {
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
  return (await response.json().catch((error: unknown) => {
    if (error instanceof DOMException && error.name === 'AbortError') throw error
    return undefined
  })) as
    | ApiSuccess<unknown>
    | ApiErrorBody
    | undefined
}

export interface ApiRequestOptions {
  /** Extra request headers (e.g. X-Season-Id). Never use for tenant identity. */
  headers?: Record<string, string>
  signal?: AbortSignal
}

async function request<TData>(
  method: string,
  path: string,
  body?: unknown,
  allowCsrfRetry = true,
  options?: ApiRequestOptions,
): Promise<ApiSuccess<TData>> {
  const headers: Record<string, string> = {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    ...(options?.headers ?? {}),
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
    response = await sessionFetch(`${baseUrl}${path}`, {
      method,
      signal: options?.signal,
      headers,
      credentials: 'include',
      body: body === undefined ? undefined : JSON.stringify(body),
    })
  } catch {
    throw new ApiError(0, undefined)
  }

  if (response.status === 419 && allowCsrfRetry) {
    resetCsrfBootstrap()
    const generation = sessionGeneration.value
    await ensureCsrfCookie()
    assertSession(generation)
    return request<TData>(method, path, body, false, options)
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

export async function apiGet<TData>(
  path: string,
  options?: ApiRequestOptions,
): Promise<ApiSuccess<TData>> {
  return request<TData>('GET', path, undefined, true, options)
}

export async function apiPost<TData>(
  path: string,
  body?: unknown,
  options?: ApiRequestOptions & { csrf?: boolean },
): Promise<ApiSuccess<TData>> {
  if (options?.csrf !== false) {
    const generation = sessionGeneration.value
    await ensureCsrfCookie()
    assertSession(generation)
  }

  return request<TData>('POST', path, body, true, options)
}

export async function apiPut<TData>(
  path: string,
  body?: unknown,
  options?: ApiRequestOptions,
): Promise<ApiSuccess<TData>> {
  const generation = sessionGeneration.value
  await ensureCsrfCookie()
  assertSession(generation)
  return request<TData>('PUT', path, body, true, options)
}

export async function apiPatch<TData>(
  path: string,
  body?: unknown,
  options?: ApiRequestOptions,
): Promise<ApiSuccess<TData>> {
  const generation = sessionGeneration.value
  await ensureCsrfCookie()
  assertSession(generation)
  return request<TData>('PATCH', path, body, true, options)
}

export async function apiDelete<TData = null>(
  path: string,
  options?: ApiRequestOptions,
): Promise<ApiSuccess<TData>> {
  const generation = sessionGeneration.value
  await ensureCsrfCookie()
  assertSession(generation)
  return request<TData>('DELETE', path, undefined, true, options)
}

/**
 * Multipart POST (e.g. document upload). Do not set Content-Type — the browser sets the boundary.
 */
export async function apiPostFormData<TData>(
  path: string,
  formData: FormData,
  allowCsrfRetry = true,
): Promise<ApiSuccess<TData>> {
  const generation = sessionGeneration.value
  await ensureCsrfCookie()
  assertSession(generation)

  const headers: Record<string, string> = {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  }

  const xsrf = readXsrfToken()
  if (xsrf) {
    headers['X-XSRF-TOKEN'] = xsrf
  }

  let response: Response
  try {
    response = await sessionFetch(`${baseUrl}${path}`, {
      method: 'POST',
      headers,
      credentials: 'include',
      body: formData,
    })
  } catch {
    throw new ApiError(0, undefined)
  }

  if (response.status === 419 && allowCsrfRetry) {
    resetCsrfBootstrap()
    const generation = sessionGeneration.value
    await ensureCsrfCookie()
    assertSession(generation)
    return apiPostFormData<TData>(path, formData, false)
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

export interface BlobDownloadResult {
  blob: Blob
  filename: string | null
}

function filenameFromContentDisposition(header: string | null): string | null {
  if (!header) {
    return null
  }

  const utf8Match = /filename\*\s*=\s*UTF-8''([^;]+)/i.exec(header)
  if (utf8Match?.[1]) {
    try {
      return decodeURIComponent(utf8Match[1].trim().replace(/^["']|["']$/g, ''))
    } catch {
      return utf8Match[1].trim().replace(/^["']|["']$/g, '')
    }
  }

  const plainMatch = /filename\s*=\s*([^;]+)/i.exec(header)
  if (plainMatch?.[1]) {
    return plainMatch[1].trim().replace(/^["']|["']$/g, '')
  }

  return null
}

/** Authorized binary download (private storage — never use a public storage URL). */
export async function apiDownloadBlob(
  path: string,
  allowCsrfRetry = true,
): Promise<BlobDownloadResult> {
  const generation = sessionGeneration.value
  await ensureCsrfCookie()
  assertSession(generation)

  const headers: Record<string, string> = {
    Accept: '*/*',
    'X-Requested-With': 'XMLHttpRequest',
  }

  const xsrf = readXsrfToken()
  if (xsrf) {
    headers['X-XSRF-TOKEN'] = xsrf
  }

  let response: Response
  try {
    response = await sessionFetch(`${baseUrl}${path}`, {
      method: 'GET',
      headers,
      credentials: 'include',
    })
  } catch {
    throw new ApiError(0, undefined)
  }

  if (response.status === 419 && allowCsrfRetry) {
    resetCsrfBootstrap()
    const generation = sessionGeneration.value
    await ensureCsrfCookie()
    assertSession(generation)
    return apiDownloadBlob(path, false)
  }

  if (!response.ok) {
    const parsed = await parseBody(response)
    throw new ApiError(
      response.status,
      parsed?.success === false ? parsed : undefined,
    )
  }

  const blob = await response.blob()
  const filename = filenameFromContentDisposition(response.headers.get('Content-Disposition'))

  return { blob, filename }
}
