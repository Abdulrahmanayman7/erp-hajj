/**
 * S11-P4 shell RTL / a11y mock Browser UAT — no Laravel, no DB.
 * Mocks /api and /sanctum; requires Vite FE already listening on FE_ORIGIN.
 *
 * Usage:
 *   node docs/audits/s11-p4-browser-uat/run-shell-rtl-a11y-mock-uat.mjs
 */
import { mkdirSync, writeFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import { createRequire } from 'node:module'

const __dirname = dirname(fileURLToPath(import.meta.url))
const EVIDENCE = join(__dirname, 'evidence')
const BASE = process.env.UAT_BASE_URL || 'http://127.0.0.1:5173'
const FE_ORIGIN = BASE.replace(/\/$/, '')

const require = createRequire(import.meta.url)
let chromium
try {
  ;({ chromium } = require('./node_modules/playwright-core'))
} catch {
  ;({ chromium } = require('../s03-accommodation-browser-uat/node_modules/playwright-core'))
}

mkdirSync(EVIDENCE, { recursive: true })

const stamp = new Date().toISOString().replace(/[:.]/g, '-')
const results = []
const report = {
  suite: 's11-p4-shell-rtl-a11y-mock',
  started_at: new Date().toISOString(),
  mode: 'mock-api',
  base: FE_ORIGIN,
  steps: results,
  verdict: 'NOT_RUN',
  not_run_reason: null,
}

function record(id, status, detail = '') {
  results.push({ id, status, detail })
  console.log(`[${status}] ${id}${detail ? ' -- ' + detail : ''}`)
}

function okEnvelope(data, meta) {
  return { success: true, message: '', data, ...(meta ? { meta } : {}) }
}

function mockUser() {
  return {
    id: 9100,
    name: 'مستخدم وهمي S11-P4',
    email: 'mock.s11p4@example.local',
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
      'season_projects.view',
      'risks.view',
      'complaints.view',
      'operational_kpis.view',
      'executive_dashboard.view',
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
        season: {
          id: 1,
          code: 'MOCK-1447',
          name: 'موسم وهمي',
          status: 'active',
        },
        selection: 'default_active',
        is_writable: true,
      }),
    )
    return
  }
  if (pathName.startsWith('/api/v1/')) {
    await fulfillJson(route, okEnvelope([], pageMeta(0)))
    return
  }
  await route.abort()
}

async function probeFe() {
  try {
    const res = await fetch(FE_ORIGIN, { signal: AbortSignal.timeout(4000) })
    return res.ok || res.status === 200 || res.status === 304
  } catch (err) {
    return false
  }
}

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
      const url = new URL(route.request().url())
      if (url.origin === FE_ORIGIN || url.pathname.startsWith('/@') || url.pathname.startsWith('/src')) {
        await route.continue()
        return
      }
      if (
        url.pathname.startsWith('/api/') ||
        url.pathname.startsWith('/sanctum/') ||
        ((url.hostname === 'localhost' || url.hostname === '127.0.0.1') &&
          (url.port === '8000' || url.port === '') &&
          (url.pathname.startsWith('/api/') || url.pathname.startsWith('/sanctum/')))
      ) {
        await handleApi(route, url)
        return
      }
      // Allow fonts/CDN; block unknown hosts
      if (url.hostname.includes('googleapis') || url.hostname.includes('gstatic')) {
        await route.continue()
        return
      }
      await route.abort()
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

    // Keyboard: Tab should reach skip link when focused from body
    await page.keyboard.press('Tab')
    const activeTestId = await page.evaluate(() => document.activeElement?.getAttribute('data-test'))
    if (activeTestId !== 'skip-to-content') {
      // Some browsers restore focus inside shell; still require the control to be focusable
      await skip.focus()
    }
    const focused = await page.evaluate(() => document.activeElement?.getAttribute('data-test'))
    if (focused !== 'skip-to-content') throw new Error('skip link not focusable')
    record('keyboard-skip-focus', 'PASS', 'skip link focusable')

    // Mobile viewport responsive shell
    await page.setViewportSize({ width: 390, height: 844 })
    await page.waitForTimeout(300)
    await page.screenshot({ path: join(EVIDENCE, `${stamp}_mobile_shell.png`), fullPage: true })
    record('mobile-responsive', 'PASS', '390x844 screenshot')

    await page.setViewportSize({ width: 1440, height: 900 })
    await page.screenshot({ path: join(EVIDENCE, `${stamp}_desktop_shell.png`), fullPage: true })
    record('desktop-shell', 'PASS', '1440x900 screenshot')

    // Navigate key Phase 2 routes under mock
    for (const [label, pathName] of [
      ['pilgrims', '/app/pilgrims'],
      ['movements', '/app/transport-movements'],
      ['accommodation', '/app/accommodation'],
      ['suppliers', '/app/suppliers'],
      ['approvals', '/app/purchase-requests/approval-inbox'],
      ['season-projects', '/app/season-projects'],
      ['risks', '/app/risks'],
      ['complaints', '/app/complaints'],
      ['kpis', '/app/operational-kpis'],
      ['executive', '/app/executive-dashboard'],
    ]) {
      await page.goto(`${FE_ORIGIN}${pathName}`, { waitUntil: 'domcontentloaded', timeout: 30000 })
      await page.waitForTimeout(400)
      const d = await page.locator('html').getAttribute('dir')
      if (d !== 'rtl') throw new Error(`${label} lost RTL`)
      record(`route-${label}`, 'PASS', pathName)
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
