import { APIRequestContext, expect, type Page } from '@playwright/test'

import { e2eEnv } from '../env'
import { firstId } from './data'

export type ApiSession = {
  request: APIRequestContext
  page: Page
  user: Record<string, unknown>
}

async function headersFor(page: Page, extra?: Record<string, string>) {
  const origin = e2eEnv.apiURL.replace(/\/$/, '')
  const cookies = await page.context().cookies()
  const raw = cookies.find((c) => c.name === 'XSRF-TOKEN')?.value
  const xsrf = raw ? decodeURIComponent(raw) : undefined
  return {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    Origin: origin,
    Referer: `${origin}/app`,
    ...(xsrf ? { 'X-XSRF-TOKEN': xsrf } : {}),
    ...(extra ?? {}),
  }
}

/**
 * Authenticated API via browser session cookies (auth.setup storageState).
 * Avoids standalone Sanctum login which returns 500 on this host.
 */
export async function apiFromPage(page: Page): Promise<ApiSession> {
  await page.goto('/app/dashboard', { waitUntil: 'domcontentloaded' })
  await page.request.get('/sanctum/csrf-cookie')
  const me = await page.request.get('/api/v1/auth/me', { headers: await headersFor(page) })
  const meBody = await me.json().catch(() => ({}))
  expect(me.ok(), `auth/me failed: ${me.status()} ${JSON.stringify(meBody)}`).toBeTruthy()
  return {
    request: page.request,
    page,
    user: (meBody?.data ?? meBody) as Record<string, unknown>,
  }
}

export async function apiGet(
  session: ApiSession,
  path: string,
  options?: { seasonId?: number | null; headers?: Record<string, string> },
) {
  const extra = {
    ...(options?.headers ?? {}),
    ...(options?.seasonId ? { 'X-Season-Id': String(options.seasonId) } : {}),
  }
  const response = await session.request.get(path, {
    headers: await headersFor(session.page, extra),
  })
  const json = await response.json().catch(() => ({}))
  return { response, json }
}

export async function apiPost(
  session: ApiSession,
  path: string,
  data: unknown,
  options?: { seasonId?: number | null; headers?: Record<string, string> },
) {
  await session.request.get('/sanctum/csrf-cookie')
  const extra = {
    ...(options?.headers ?? {}),
    ...(options?.seasonId ? { 'X-Season-Id': String(options.seasonId) } : {}),
  }
  const response = await session.request.post(path, {
    data,
    headers: await headersFor(session.page, extra),
  })
  const json = await response.json().catch(() => ({}))
  return { response, json }
}

export async function apiPatch(
  session: ApiSession,
  path: string,
  data: unknown,
  options?: { seasonId?: number | null; headers?: Record<string, string> },
) {
  await session.request.get('/sanctum/csrf-cookie')
  const extra = {
    ...(options?.headers ?? {}),
    ...(options?.seasonId ? { 'X-Season-Id': String(options.seasonId) } : {}),
  }
  const response = await session.request.patch(path, {
    data,
    headers: await headersFor(session.page, extra),
  })
  const json = await response.json().catch(() => ({}))
  return { response, json }
}

/** Prefer active season; fall back to first listed season. */
export async function resolveSeasonId(session: ApiSession): Promise<number | null> {
  const active = await apiGet(session, '/api/v1/seasons?per_page=5&status=active')
  const fromActive = firstId(active.json)
  if (fromActive) return fromActive
  const any = await apiGet(session, '/api/v1/seasons?per_page=5')
  return firstId(any.json)
}
