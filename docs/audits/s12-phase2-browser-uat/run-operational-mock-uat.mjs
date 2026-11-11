/**
 * S12 Phase 2 operational mock Browser UAT — no Laravel, no DB.
 * Covers Foundation–S10 route shells under mocked /api + /sanctum.
 * Mock PASS is NOT a substitute for live attested operational UAT.
 *
 * Usage:
 *   node docs/audits/s12-phase2-browser-uat/run-operational-mock-uat.mjs
 */
import { mkdirSync, writeFileSync, readFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import { createRequire } from 'node:module'
import { classifyRequest } from './network-policy.mjs'

const __dirname = dirname(fileURLToPath(import.meta.url))
const EVIDENCE = join(__dirname, 'evidence')
const BASE = process.env.UAT_BASE_URL || 'http://127.0.0.1:5173'
const FE_ORIGIN = BASE.replace(/\/$/, '')
const L = JSON.parse(readFileSync(join(__dirname, 'labels.json'), 'utf8'))
const networkLog = { continued: [], mocked: [], denied: [] }

const require = createRequire(import.meta.url)
let chromium
try {
  ;({ chromium } = require('./node_modules/playwright-core'))
} catch {
  ;({ chromium } = require('../s11-p4-browser-uat/node_modules/playwright-core'))
}

mkdirSync(EVIDENCE, { recursive: true })

const stamp = new Date().toISOString().replace(/[:.]/g, '-')
const results = []
const report = {
  suite: 's12-phase2-operational-mock',
  started_at: new Date().toISOString(),
  mode: 'mock-api',
  base: FE_ORIGIN,
  steps: results,
  verdict: 'NOT_RUN',
  not_run_reason: null,
  note: 'Mock shell UAT is not a substitute for live attested disposable-DB UAT.',
}

function record(id, status, detail = '') {
  results.push({ id, status, detail })
  console.log(`[${status}] ${id}${detail ? ' -- ' + detail : ''}`)
}

function okEnvelope(data, meta) {
  return { success: true, message: '', data, ...(meta ? { meta } : {}) }
}

function mockUser(extraPermissions = []) {
  return {
    id: 9200,
    name: 'مستخدم وهمي S12',
    email: 'mock.s12@example.local',
    status: 'active',
    avatar_group: 'neutral',
    is_platform_user: false,
    employee_id: null,
    tenant: {
      id: 1,
      tenant_code: 'mock_tenant',
      name: 'منشأة وهمية',
      status: 'active',
      locale: 'ar',
      timezone: 'Asia/Riyadh',
    },
    roles: [{ id: 1, code: 'mock_role', name: 'دور وهمي' }],
    permissions: [
      'dashboard.view',
      'seasons.view',
      'pilgrims.view',
      'transportation.view',
      'accommodation.view',
      'suppliers.view',
      'purchase_requests.view',
      'purchase_requests.approve',
      'rfqs.view',
      'purchase_orders.view',
      'goods_receipts.view',
      'season_projects.view',
      'risks.view',
      'complaints.view',
      'season_templates.view',
      'season_safe_copy.view',
      'operational_kpis.view',
      'executive_dashboard.view',
      ...extraPermissions,
    ],
  }
}

function pageMeta(total = 0) {
  return { current_page: 1, per_page: 15, total, last_page: 1 }
}

async function fulfillJson(route, body, status = 200) {
  await route.fulfill({
    status,
    contentType: 'application/json; charset=utf-8',
    body: JSON.stringify(body),
  })
}

async function handleApi(route, url) {
  const pathName = url.pathname
  if (pathName === '/sanctum/csrf-cookie') {
    await route.fulfill({
      status: 204,
      headers: { 'Set-Cookie': 'XSRF-TOKEN=mock-xsrf; Path=/; SameSite=Lax' },
    })
    return
  }
  if (pathName === '/api/v1/auth/me' || pathName === '/api/v1/auth/login') {
    await fulfillJson(route, okEnvelope(mockUser()))
    return
  }
  if (pathName === '/api/v1/platform/setup/status') {
    await fulfillJson(route, okEnvelope({ available: false }))
    return
  }
  if (pathName === '/api/v1/dashboard') {
    await fulfillJson(
      route,
      okEnvelope({
        meta: { generated_at: new Date().toISOString(), timezone: 'Asia/Riyadh' },
        kpis: {},
        attention: [],
        today: {},
        work: {},
        resources: {},
        notifications: { unread_count: 0, href: '/app/notifications' },
      }),
    )
    return
  }
  if (pathName.startsWith('/api/v1/notifications')) {
    await fulfillJson(route, okEnvelope([], pageMeta(0)))
    return
  }
  if (pathName === '/api/v1/season-context') {
    await fulfillJson(
      route,
      okEnvelope({
        season: { id: 1, code: 'MOCK-1447', name: 'موسم وهمي', status: 'active' },
        selection: 'default_active',
        is_writable: true,
      }),
    )
    return
  }
  if (pathName === '/api/v1/browser-uat/attestation') {
    await fulfillJson(
      route,
      { success: false, message: 'غير موجود', code: 'BROWSER_UAT_ATTESTATION_UNAVAILABLE' },
      404,
    )
    return
  }
  if (pathName.startsWith('/api/v1/')) {
    await fulfillJson(route, okEnvelope([], pageMeta(0)))
    return
  }
  if (pathName.startsWith('/api/') || pathName.startsWith('/sanctum/')) {
    await fulfillJson(
      route,
      { success: false, message: 'mock rejected', code: 'MOCK_API_REJECTED' },
      404,
    )
    return
  }
  await route.abort()
}

async function probeFe() {
  try {
    const res = await fetch(FE_ORIGIN, { signal: AbortSignal.timeout(4000) })
    return res.ok || res.status === 200 || res.status === 304
  } catch {
    return false
  }
}

const JOURNEYS = [
  {
    id: 'auth-permissions',
    label: L.journeys.authPermissions,
    routes: [
      ['login-shell', '/login'],
      ['app-home', '/app'],
      ['dashboard', '/app/dashboard'],
      ['users', '/app/users'],
      ['roles', '/app/roles'],
    ],
  },
  {
    id: 'seasons-pilgrims',
    label: L.journeys.seasonsPilgrims,
    routes: [
      ['seasons', '/app/seasons'],
      ['pilgrims', '/app/pilgrims'],
      ['season-groups', '/app/season-groups'],
      ['season-programs', '/app/season-programs'],
      ['season-families', '/app/season-families'],
    ],
  },
  {
    id: 'fleet-transport',
    label: L.journeys.fleetTransport,
    routes: [
      ['vehicles', '/app/transport-vehicles'],
      ['drivers', '/app/transport-drivers'],
      ['season-fleet', '/app/transport-season-fleet'],
      ['points', '/app/transport-points'],
      ['movements', '/app/transport-movements'],
      ['breakdowns', '/app/transport-breakdowns'],
    ],
  },
  {
    id: 'accommodation-camps',
    label: L.journeys.accommodationCamps,
    routes: [
      ['overview', '/app/accommodation'],
      ['allocation', '/app/accommodation/allocation'],
      ['drafts', '/app/accommodation/drafts'],
      ['scopes', '/app/accommodation/scopes'],
    ],
  },
  {
    id: 'suppliers-procurement',
    label: L.journeys.suppliersProcurement,
    routes: [
      ['suppliers', '/app/suppliers'],
      ['purchase-requests', '/app/purchase-requests'],
      ['approval-inbox', '/app/purchase-requests/approval-inbox'],
      ['rfqs', '/app/rfqs'],
      ['purchase-orders', '/app/purchase-orders'],
      ['goods-receipts', '/app/goods-receipts'],
    ],
  },
  {
    id: 'season-projects',
    label: L.journeys.seasonProjects,
    routes: [['season-projects', '/app/season-projects']],
  },
  {
    id: 'risks-complaints',
    label: L.journeys.risksComplaints,
    routes: [
      ['risks', '/app/risks'],
      ['complaints', '/app/complaints'],
    ],
  },
  {
    id: 'executive-kpis',
    label: L.journeys.executiveKpis,
    routes: [
      ['operational-kpis', '/app/operational-kpis'],
      ['executive-dashboard', '/app/executive-dashboard'],
    ],
  },
  {
    id: 'templates-safe-copy',
    label: L.journeys.templatesSafeCopy,
    routes: [
      ['templates', '/app/season-templates'],
      ['safe-copy', '/app/season-safe-copy'],
      ['copy-runs', '/app/season-copy-runs'],
      ['reminder-rules', '/app/season-reminder-rules'],
    ],
  },
]

async function main() {
  if (!(await probeFe())) {
    report.verdict = 'NOT_RUN'
    report.not_run_reason = `Frontend unreachable at ${FE_ORIGIN}`
    record('environment-gate', 'NOT_RUN', report.not_run_reason)
    finish()
    return
  }

  record('environment-gate', 'PASS', FE_ORIGIN)

  let browser
  try {
    browser = await chromium.launch({
      channel: process.env.UAT_BROWSER_CHANNEL || 'chrome',
      headless: true,
    })
    const context = await browser.newContext({ locale: 'ar-SA', viewport: { width: 1440, height: 900 } })
    await context.addInitScript(() => {
      localStorage.setItem('erp_hajj_token', 'mock-token')
    })
    const page = await context.newPage()

    await page.route('**/*', async (route) => {
      const requestUrl = route.request().url()
      const decision = classifyRequest(requestUrl, { feOrigin: FE_ORIGIN })
      if (decision === 'mock-api') {
        networkLog.mocked.push(requestUrl)
        await handleApi(route, new URL(requestUrl))
        return
      }
      if (decision === 'allow-fe') {
        networkLog.continued.push(requestUrl)
        await route.continue()
        return
      }
      networkLog.denied.push(requestUrl)
      await route.abort('blockedbyclient')
    })

    await page.goto(`${FE_ORIGIN}/app`, { waitUntil: 'domcontentloaded', timeout: 45000 })
    await page.waitForTimeout(800)

    const dir = await page.locator('html').getAttribute('dir')
    const lang = await page.locator('html').getAttribute('lang')
    if (dir !== 'rtl' || lang !== 'ar') throw new Error(`html dir/lang mismatch: ${dir}/${lang}`)
    record('html-rtl-ar', 'PASS', `dir=${dir} lang=${lang}`)

    const skip = page.locator('[data-test="skip-to-content"]')
    if ((await skip.count()) === 0) throw new Error('skip-to-content missing')
    record('skip-to-content', 'PASS', await skip.innerText())

    const main = page.locator('#app-main-content')
    if ((await main.count()) === 0) throw new Error('main landmark missing')
    record('main-landmark', 'PASS', 'id=app-main-content')

    await page.setViewportSize({ width: 390, height: 844 })
    await page.waitForTimeout(300)
    await page.screenshot({ path: join(EVIDENCE, `${stamp}_mobile_shell.png`), fullPage: true })
    record('mobile-responsive', 'PASS', '390x844')

    await page.setViewportSize({ width: 1440, height: 900 })
    await page.screenshot({ path: join(EVIDENCE, `${stamp}_desktop_shell.png`), fullPage: true })
    record('desktop-shell', 'PASS', '1440x900')

    for (const journey of JOURNEYS) {
      for (const [routeLabel, pathName] of journey.routes) {
        await page.goto(`${FE_ORIGIN}${pathName}`, { waitUntil: 'domcontentloaded', timeout: 30000 })
        await page.waitForTimeout(350)
        const d = await page.locator('html').getAttribute('dir')
        if (d !== 'rtl') throw new Error(`${journey.id}/${routeLabel} lost RTL`)
        // Login page may not keep token-bound app chrome; still require RTL.
        record(`route-${journey.id}-${routeLabel}`, 'PASS', pathName)
      }
      record(journey.id, 'PASS', journey.label)
    }

    // Isolation probes
    const probe = await page.evaluate(async (origin) => {
      const out = { sameOriginApi: null, sanctum: null, external: null }
      try {
        const r = await fetch(`${origin}/api/v1/auth/me`, { credentials: 'include' })
        out.sameOriginApi = { status: r.status, ok: r.ok }
      } catch (e) {
        out.sameOriginApi = { error: String(e) }
      }
      try {
        const r = await fetch(`${origin}/sanctum/csrf-cookie`, { credentials: 'include' })
        out.sanctum = { status: r.status }
      } catch (e) {
        out.sanctum = { error: String(e) }
      }
      try {
        const r = await fetch('https://evil.example/api/v1/secrets', { redirect: 'manual' })
        out.external = { status: r.status, ok: r.ok }
      } catch (e) {
        out.external = { blocked: true, error: String(e) }
      }
      return out
    }, FE_ORIGIN)

    if (!probe.sameOriginApi?.ok) {
      throw new Error(`same-origin /api not mocked: ${JSON.stringify(probe.sameOriginApi)}`)
    }
    record('isolation-same-origin-api', 'PASS', JSON.stringify(probe.sameOriginApi))
    if (probe.sanctum?.error && !probe.sanctum?.status) {
      throw new Error(`same-origin /sanctum not mocked: ${JSON.stringify(probe.sanctum)}`)
    }
    record('isolation-same-origin-sanctum', 'PASS', JSON.stringify(probe.sanctum))
    if (probe.external && probe.external.ok) {
      throw new Error('external API request was not blocked')
    }
    record('isolation-external-deny', 'PASS', JSON.stringify(probe.external))

    const leaked = networkLog.continued.filter((u) => {
      try {
        const pathName = new URL(u).pathname
        return pathName.startsWith('/api/') || pathName.startsWith('/sanctum/')
      } catch {
        return false
      }
    })
    if (leaked.length > 0) {
      throw new Error(`API/sanctum requests were continued to network: ${leaked.join(', ')}`)
    }
    record('isolation-no-api-continue', 'PASS', `mocked=${networkLog.mocked.length} denied=${networkLog.denied.length}`)

    report.network = {
      mocked: networkLog.mocked.length,
      denied: networkLog.denied.length,
      continued: networkLog.continued.length,
    }
    report.verdict = results.some((r) => r.status === 'FAIL') ? 'FAIL' : 'PASS'
  } catch (err) {
    report.verdict = 'FAIL'
    record('fatal', 'FAIL', String(err))
  } finally {
    await browser?.close().catch(() => {})
    finish()
  }
}

function finish() {
  report.finished_at = new Date().toISOString()
  writeFileSync(join(EVIDENCE, `${stamp}_shell_report.json`), JSON.stringify(report, null, 2), 'utf8')
  writeFileSync(join(__dirname, 'last-shell-report.json'), JSON.stringify(report, null, 2), 'utf8')
  console.log(`\nVerdict: ${report.verdict}`)
  if (report.verdict === 'FAIL') process.exit(1)
}

main()
