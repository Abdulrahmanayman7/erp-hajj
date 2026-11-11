/**
 * S11-P4 operational Browser UAT (real API + disposable UAT DB only).
 *
 * Required safe environment:
 *   - Frontend at UAT_BASE_URL (default http://127.0.0.1:5173)
 *   - Laravel API at UAT_API_URL (default http://127.0.0.1:8000)
 *   - GET /api/v1/browser-uat/attestation must return the live connection database
 *     (BROWSER_UAT_ATTESTATION_ENABLED=true, APP_ENV local|testing, shared token).
 *     Health never attests DB. Token is sent only to that attestation URL.
 *   - Attested name must match /^erp_hajj_.+_uat$/
 *   - Optional UAT_DATABASE is only an expected-value check against the attestation
 *
 * If the environment is missing or not verified disposable → writes NOT_RUN report and exits 0.
 */
import { chromium } from 'playwright-core'
import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
import {
  ATTESTATION_PATH,
  attestationRequestHeaders,
  evaluateAttestation,
} from './uat-attestation.mjs'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const EVIDENCE = path.join(__dirname, 'evidence')
const L = JSON.parse(fs.readFileSync(path.join(__dirname, 'labels.json'), 'utf8'))
const BASE = process.env.UAT_BASE_URL || 'http://127.0.0.1:5173'
const API = process.env.UAT_API_URL || 'http://127.0.0.1:8000'
const ATTESTATION_TOKEN =
  process.env.BROWSER_UAT_ATTESTATION_TOKEN || process.env.UAT_ATTESTATION_TOKEN || ''
const OWNER = {
  email: process.env.UAT_OWNER_EMAIL || 'uat.owner@example.com',
  password: process.env.UAT_OWNER_PASSWORD || 'Admin@12345',
}
const VIEWER = {
  email: process.env.UAT_VIEWER_EMAIL || 'uat.viewer@example.com',
  password: process.env.UAT_VIEWER_PASSWORD || 'Viewer@12345',
}

const stamp = new Date().toISOString().replace(/[:.]/g, '-')
const report = {
  suite: 's11-p4-browser-uat',
  started_at: new Date().toISOString(),
  isolation: { database: null, verified_disposable: false },
  env: { base: BASE, api: API },
  steps: [],
  verdict: 'NOT_RUN',
  not_run_reason: null,
}

fs.mkdirSync(EVIDENCE, { recursive: true })

function record(step, status, detail = '', shot = null) {
  report.steps.push({ step, status, detail, screenshot: shot })
  console.log(`[${status}] ${step}${detail ? ' -- ' + detail : ''}`)
}

async function shot(page, name) {
  const file = `${stamp}_${name}.png`
  await page.screenshot({ path: path.join(EVIDENCE, file), fullPage: true })
  return file
}

async function probeJson(url, timeoutMs = 4000, extraHeaders = {}) {
  const ctrl = new AbortController()
  const timer = setTimeout(() => ctrl.abort(), timeoutMs)
  try {
    const res = await fetch(url, {
      signal: ctrl.signal,
      redirect: 'manual',
      headers: { ...extraHeaders },
    })
    if (res.status >= 300 && res.status < 400) {
      return {
        ok: false,
        status: res.status,
        json: null,
        headers: res.headers,
        error: `Redirect ${res.status} to ${res.headers.get('location') || '(none)'} — fail closed`,
      }
    }
    const text = await res.text()
    let json = null
    try {
      json = JSON.parse(text)
    } catch {
      json = null
    }
    return { ok: res.ok, status: res.status, json, headers: res.headers, rawText: text }
  } catch (err) {
    return { ok: false, status: 0, json: null, headers: null, error: String(err) }
  } finally {
    clearTimeout(timer)
  }
}

async function verifySafeEnvironment() {
  const fe = await probeJson(BASE)
  if (!fe.ok && fe.status === 0) {
    return {
      ok: false,
      reason: `Frontend unreachable at ${BASE} (${fe.error || 'timeout/connection refused'})`,
    }
  }

  const health = await probeJson(`${API}/api/v1/health`)
  if (!health.ok && health.status === 0) {
    return {
      ok: false,
      reason: `Laravel API unreachable at ${API} (${health.error || 'timeout/connection refused'})`,
    }
  }
  if (health.error?.includes('Redirect')) {
    return { ok: false, reason: `Health probe redirect — fail closed (${health.error})` }
  }

  // Never trust UAT_DATABASE or health for DB identity. Attestation only.
  // Token header is attached solely for the attestation URL under the configured API origin.
  if (!ATTESTATION_TOKEN) {
    return {
      ok: false,
      reason:
        'BROWSER_UAT_ATTESTATION_TOKEN (or UAT_ATTESTATION_TOKEN) is required for live UAT. Fail closed.',
    }
  }
  const attestationUrl = `${API.replace(/\/$/, '')}${ATTESTATION_PATH}`
  const tokenHeaders = attestationRequestHeaders(attestationUrl, API, ATTESTATION_TOKEN)
  if (!Object.keys(tokenHeaders).length) {
    return {
      ok: false,
      reason: 'Refusing to send attestation token outside the configured attestation endpoint.',
    }
  }
  const attestation = await probeJson(attestationUrl, 4000, tokenHeaders)
  if (attestation.error?.includes('Redirect')) {
    return { ok: false, reason: `Attestation redirect — fail closed (${attestation.error})` }
  }
  if (attestation.status === 0) {
    return {
      ok: false,
      reason: `Attestation unreachable (${attestation.error || 'timeout'}). Fail closed.`,
    }
  }
  if (attestation.json === null && attestation.status !== 0) {
    return {
      ok: false,
      reason: `Attestation non-JSON response (status=${attestation.status}). Fail closed.`,
    }
  }

  const verdict = evaluateAttestation(attestation.json, {
    ok: attestation.ok,
    status: attestation.status,
    expectedDatabase: process.env.UAT_DATABASE || null,
  })

  if (!verdict.ok) {
    return { ok: false, reason: verdict.reason }
  }

  return { ok: true, database: verdict.database }
}

async function login(page, user) {
  await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' })
  await page.waitForSelector('#login-email', { timeout: 20000 })
  await page.locator('#login-email').fill(user.email)
  await page.locator('#login-password').fill(user.password)
  await page.locator('button[type="submit"]').click()
  await page.waitForURL((url) => /\/(app|platform)(\/|$)/.test(url.pathname), { timeout: 30000 })
  if (page.url().includes('/platform')) {
    throw new Error(`Login landed on platform for ${user.email}`)
  }
}

async function assertRtl(page) {
  const dir = await page.locator('html').getAttribute('dir')
  const lang = await page.locator('html').getAttribute('lang')
  if (dir !== 'rtl' || lang !== 'ar') {
    throw new Error(`Expected html[dir=rtl][lang=ar], got dir=${dir} lang=${lang}`)
  }
}

async function visit(page, pathName, label) {
  await page.goto(`${BASE}${pathName}`, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(500)
  await assertRtl(page)
  const file = await shot(page, label)
  return file
}

async function runJourneys(browser) {
  const context = await browser.newContext({ locale: 'ar-SA', viewport: { width: 1440, height: 900 } })
  const page = await context.newPage()

  // 1) Login + permission-aware navigation
  await login(page, OWNER)
  await assertRtl(page)
  const skip = page.locator('[data-test="skip-to-content"]')
  if ((await skip.count()) === 0) throw new Error('Missing skip-to-content control')
  record(L.journeys.loginNav, 'PASS', 'owner login + skip link', await shot(page, '01_logged_in'))

  // 2) Season / tenant shell
  await visit(page, '/app/seasons', '02_seasons')
  record(L.journeys.seasonTenant, 'PASS', 'seasons page RTL')

  // 3) Pilgrim → Transport → Movements
  await visit(page, '/app/pilgrims', '03_pilgrims')
  await visit(page, '/app/transport-movements', '04_movements')
  record(L.journeys.pilgrimTransport, 'PASS', 'pilgrims + movements')

  // 4) Pilgrim → Accommodation occupancy
  await visit(page, '/app/accommodation', '05_accommodation')
  record(L.journeys.pilgrimAccommodation, 'PASS', 'accommodation overview')

  // 5) Suppliers → Procurement approvals
  await visit(page, '/app/suppliers', '06_suppliers')
  await visit(page, '/app/purchase-requests/approval-inbox', '07_approvals')
  record(L.journeys.procurementApprovals, 'PASS', 'suppliers + approval inbox')

  // 6) Seasonal Projects → Baseline
  await visit(page, '/app/season-projects', '08_season_projects')
  record(L.journeys.seasonProjectsBaseline, 'PASS', 'season projects list')

  // 7) Risks / Complaints → Alerts (executive)
  await visit(page, '/app/risks', '09_risks')
  await visit(page, '/app/complaints', '10_complaints')
  await visit(page, '/app/executive-dashboard', '11_executive_alerts')
  record(L.journeys.risksAlerts, 'PASS', 'risks/complaints/executive')

  // 8) Operational KPIs → Executive Dashboard
  await visit(page, '/app/operational-kpis', '12_kpis')
  await visit(page, '/app/executive-dashboard', '13_dashboard')
  record(L.journeys.kpisDashboard, 'PASS', 'kpis + executive dashboard')

  // Viewer permission-aware: login as viewer and ensure executive dual-gate still RTL-safe
  await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' })
  await login(page, VIEWER)
  await visit(page, '/app', '14_viewer_home')
  record('viewer-nav', 'PASS', 'viewer session RTL shell')

  await context.close()
}

async function main() {
  const env = await verifySafeEnvironment()
  if (!env.ok) {
    report.verdict = 'NOT_RUN'
    report.not_run_reason = env.reason
    record('environment-gate', 'NOT_RUN', env.reason)
    finish()
    return
  }

  report.isolation.database = env.database
  report.isolation.verified_disposable = true
  record('environment-gate', 'PASS', `disposable DB ${env.database}`)

  let browser
  try {
    browser = await chromium.launch({
      channel: process.env.UAT_BROWSER_CHANNEL || 'chrome',
      headless: true,
    })
    await runJourneys(browser)
    report.verdict = report.steps.some((s) => s.status === 'FAIL') ? 'FAIL' : 'PASS'
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
  const out = path.join(EVIDENCE, `${stamp}_report.json`)
  fs.writeFileSync(out, JSON.stringify(report, null, 2), 'utf8')
  fs.writeFileSync(path.join(__dirname, 'last-report.json'), JSON.stringify(report, null, 2), 'utf8')
  console.log(`\nVerdict: ${report.verdict}`)
  console.log(`Report: ${out}`)
  // NOT_RUN is non-fatal for local harness; FAIL exits non-zero.
  if (report.verdict === 'FAIL') process.exit(1)
}

main()
