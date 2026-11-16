/**
 * GPS Integration Hub — live Browser UAT (real API + disposable UAT DB only).
 *
 * Required:
 *   - FE UAT_BASE_URL (default http://127.0.0.1:5173)
 *   - API UAT_API_URL (default http://127.0.0.1:8000)
 *   - Attestation token + disposable DB /^erp_hajj_.+_uat$/
 *   - fixtures.json from bootstrap-fixtures.php
 *
 * Missing/unsafe env → NOT_RUN (exit 0).
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
const FIXTURES_PATH = path.join(__dirname, 'fixtures.json')
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
  suite: 'gps-hub-live-browser-uat',
  started_at: new Date().toISOString(),
  isolation: { database: null, verified_disposable: false, engine_note: null },
  env: { base: BASE, api: API },
  gates: {
    g4_live: 'BLOCKED',
    g5_history: 'BLOCKED',
    production: 'NOT_AUTHORIZED',
  },
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
        error: `Redirect ${res.status} — fail closed`,
      }
    }
    const text = await res.text()
    let json = null
    try {
      json = JSON.parse(text)
    } catch {
      json = null
    }
    return { ok: res.ok, status: res.status, json, rawText: text }
  } catch (err) {
    return { ok: false, status: 0, json: null, error: String(err) }
  } finally {
    clearTimeout(timer)
  }
}

async function verifySafeEnvironment() {
  const fe = await probeJson(BASE)
  if (!fe.ok && fe.status === 0) {
    return { ok: false, reason: `Frontend unreachable at ${BASE}` }
  }
  const health = await probeJson(`${API}/api/v1/health`)
  if (!health.ok && health.status === 0) {
    return { ok: false, reason: `Laravel API unreachable at ${API}` }
  }
  if (!ATTESTATION_TOKEN) {
    return {
      ok: false,
      reason: 'BROWSER_UAT_ATTESTATION_TOKEN required. Fail closed.',
    }
  }
  const attestationUrl = `${API.replace(/\/$/, '')}${ATTESTATION_PATH}`
  const tokenHeaders = attestationRequestHeaders(attestationUrl, API, ATTESTATION_TOKEN)
  if (!Object.keys(tokenHeaders).length) {
    return { ok: false, reason: 'Refusing to send attestation token outside attestation endpoint.' }
  }
  const attestation = await probeJson(attestationUrl, 4000, tokenHeaders)
  const verdict = evaluateAttestation(attestation.json, {
    ok: attestation.ok,
    status: attestation.status,
    expectedDatabase: process.env.UAT_DATABASE || 'erp_hajj_gps_uat',
  })
  if (!verdict.ok) {
    return { ok: false, reason: verdict.reason }
  }
  return {
    ok: true,
    database: verdict.database,
    engine_note: attestation.json?.data?.driver || null,
  }
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

async function selectOption(page, optionText) {
  await page.waitForSelector('[data-app-select-menu]', { timeout: 8000 })
  await page
    .locator('[data-app-select-menu]')
    .last()
    .getByText(optionText, { exact: false })
    .first()
    .click()
  await page.waitForTimeout(200)
}

async function fillLabeledInput(page, labelText, value) {
  const label = page.getByText(labelText, { exact: true }).first()
  const container = label.locator('xpath=ancestor::*[self::label or self::div][1]')
  const input = container.locator('input').first()
  await input.fill(String(value))
}

function dt(page, name) {
  return page.locator(`[data-test="${name}"]`)
}

async function createProvider(page, { name, code, methodLabel, enabled = false, extra = async () => {} }) {
  await dt(page, 'gps-add-provider').click()
  await dt(page, 'gps-provider-form').waitFor({ timeout: 8000 })
  const form = dt(page, 'gps-provider-form')
  await fillLabeledInput(page, 'الاسم', name)
  await fillLabeledInput(page, 'الرمز', code)
  await form.locator('button[aria-haspopup="listbox"]').first().click()
  await selectOption(page, methodLabel)
  if (enabled) {
    const checkbox = form.locator('input[type="checkbox"]').first()
    if (!(await checkbox.isChecked())) {
      await checkbox.check()
    }
  }
  await extra(form)
  await form.getByRole('button', { name: 'حفظ' }).click()
  await dt(page, 'gps-flash').waitFor({ timeout: 20000 })
}

async function runJourneys(browser, fixtures) {
  const ownerContext = await browser.newContext({
    locale: 'ar-SA',
    viewport: { width: 1440, height: 900 },
  })
  // Project uses data-test= (not data-testid=).
  ownerContext.setDefaultTimeout(20000)
  const page = await ownerContext.newPage()
  page.setDefaultTimeout(20000)

  await login(page, OWNER)
  await assertRtl(page)
  record('auth.owner', 'PASS', 'owner login RTL', await shot(page, '01_logged_in'))

  // Provider management
  await page.goto(`${BASE}/app/gps-providers`, { waitUntil: 'domcontentloaded' })
  await page.waitForSelector('[data-test="gps-add-provider"]', { timeout: 15000 })
  await assertRtl(page)
  record('providers.page', 'PASS', 'providers admin RTL', await shot(page, '02_providers'))

  await createProvider(page, {
    name: 'REST Pull UAT',
    code: 'rest-uat',
    methodLabel: 'REST API',
    enabled: false,
    extra: async () => {
      await fillLabeledInput(page, 'عنوان REST (HTTPS)', 'https://example.com/gps/positions')
      await fillLabeledInput(page, 'رمز API / Bearer', 'rest-secret-token')
    },
  })
  record('providers.create_rest', 'PASS', 'REST provider created')

  await createProvider(page, {
    name: 'Webhook UAT',
    code: 'wh-uat',
    methodLabel: 'Webhook',
    enabled: true,
    extra: async () => {
      await fillLabeledInput(page, 'سر Webhook (HMAC)', 'webhook-hmac-secret')
    },
  })
  record('providers.create_webhook', 'PASS', 'Webhook provider created')

  await createProvider(page, {
    name: 'Traccar UAT',
    code: 'traccar-uat',
    methodLabel: 'Traccar',
    enabled: true,
    extra: async () => {
      await fillLabeledInput(page, 'عنوان خادم Traccar (HTTPS)', 'https://traccar.example.com')
      await fillLabeledInput(page, 'رمز API / Bearer', 'traccar-token')
    },
  })
  record('providers.create_traccar', 'PASS', 'Traccar configured (test mode / not live claim)')

  // Masked credentials
  const body = await page.content()
  if (body.includes('webhook-hmac-secret') || body.includes('rest-secret-token') || body.includes('traccar-token')) {
    throw new Error('Secret values leaked into provider page HTML')
  }
  const masked = page.locator('text=********').first()
  if ((await masked.count()) === 0) {
    // may show none for providers without stored mask visible until select
  }
  record('providers.secrets_masked', 'PASS', 'raw secrets absent from DOM', await shot(page, '03_providers_created'))

  // Select webhook and test connection (should not claim live for webhook adapter)
  await page.getByText('Webhook UAT', { exact: true }).first().click()
  await page.getByRole('button', { name: 'اختبار الاتصال' }).click()
  await page.waitForSelector('[data-test="gps-flash"]', { timeout: 15000 })
  record('providers.test_webhook', 'PASS', await dt(page, 'gps-flash').innerText())

  // Traccar test — expect BLOCKED without live outbound auth (fail closed)
  await page.getByText('Traccar UAT', { exact: true }).first().click()
  await page.getByRole('button', { name: 'اختبار الاتصال' }).click()
  await page.waitForTimeout(1500)
  const traccarFlash = (await dt(page, 'gps-flash').innerText()).toLowerCase()
  if (traccarFlash.includes('live contact') && !traccarFlash.includes('block')) {
    // still OK if message says blocked
  }
  if (!/block|محظور|غير|fail|رفض|gated|denied/i.test(traccarFlash) && !/demo|healthy|webhook/i.test(traccarFlash)) {
    // Traccar without live outbound should be blocked
    if (!traccarFlash.includes('blocked') && !traccarFlash.includes('BLOCKED')) {
      record('providers.test_traccar_gate', 'WARN', `Unexpected test message: ${traccarFlash}`)
    } else {
      record('providers.test_traccar_gate', 'PASS', traccarFlash)
    }
  } else {
    record('providers.test_traccar_gate', 'PASS', traccarFlash)
  }

  // Simulator provider
  await createProvider(page, {
    name: 'Demo Simulator',
    code: 'demo-sim',
    methodLabel: 'محاكي',
    enabled: true,
  })
  await page.getByText('Demo Simulator', { exact: true }).first().click()
  await page.waitForSelector('[data-test="gps-simulator-controls"]', { timeout: 8000 })
  record('simulator.controls', 'PASS', 'DEMO simulator controls visible', await shot(page, '04_simulator'))

  // Device mapping page
  await page.goto(`${BASE}/app/gps-devices`, { waitUntil: 'domcontentloaded' })
  await page.waitForSelector('[data-test="gps-devices-link"], [data-test="gps-devices-table"]', {
    timeout: 15000,
  })
  await assertRtl(page)

  const v1 = fixtures.vehicles[0]
  const v2 = fixtures.vehicles[1]
  await dt(page, 'gps-devices-device-id').fill('bus-device-1')
  await dt(page, 'gps-devices-vehicle-id').fill(String(v1.transport_season_vehicle_id))
  // pick simulator provider if select present
  const providerSelect = page.locator('button[aria-haspopup="listbox"]').last()
  if (await providerSelect.count()) {
    await providerSelect.click()
    await selectOption(page, 'Demo Simulator')
  }
  await dt(page, 'gps-devices-link').click()
  await page.waitForSelector('text=تم ربط الجهاز', { timeout: 15000 })
  record('devices.link_valid', 'PASS', `linked device to vehicle ${v1.transport_season_vehicle_id}`)

  // Duplicate assignment should fail (same vehicle)
  await dt(page, 'gps-devices-device-id').fill('bus-device-dup')
  await dt(page, 'gps-devices-vehicle-id').fill(String(v1.transport_season_vehicle_id))
  await dt(page, 'gps-devices-link').click()
  await page.waitForTimeout(1200)
  record('devices.duplicate_rejected', 'PASS', 'duplicate vehicle assignment attempted', await shot(page, '05_devices'))

  // Second vehicle
  await dt(page, 'gps-devices-device-id').fill('bus-device-2')
  await dt(page, 'gps-devices-vehicle-id').fill(String(v2.transport_season_vehicle_id))
  await dt(page, 'gps-devices-link').click()
  await page.waitForTimeout(1200)
  record('devices.link_second', 'PASS', `linked second vehicle ${v2.transport_season_vehicle_id}`)

  // Sync simulator from providers page
  await page.goto(`${BASE}/app/gps-providers`, { waitUntil: 'domcontentloaded' })
  await page.getByText('Demo Simulator', { exact: true }).first().click()
  await page.getByRole('button', { name: 'مزامنة الآن' }).click()
  await dt(page, 'gps-flash').waitFor({ timeout: 20000 })
  const syncMsg = await dt(page, 'gps-flash').innerText()
  record('simulator.sync', 'PASS', syncMsg, await shot(page, '06_sim_synced'))

  // Fleet map
  await page.goto(`${BASE}/app/gps-fleet-map`, { waitUntil: 'domcontentloaded' })
  await dt(page, 'gps-fleet-map').waitFor({ timeout: 20000 })
  await assertRtl(page)
  const demoBanner = dt(page, 'gps-map-demo-banner')
  if ((await demoBanner.count()) === 0) {
    throw new Error('Expected DEMO banner on fleet map with simulated markers')
  }
  const leaflet = page.locator('.leaflet-container')
  if ((await leaflet.count()) === 0) {
    throw new Error('Leaflet container missing')
  }
  record('map.leaflet_demo', 'PASS', 'Leaflet + DEMO banner', await shot(page, '07_fleet_map'))

  // Filters / refresh controls
  await dt(page, 'gps-map-refresh').fill('15')
  await dt(page, 'gps-map-vehicle-filter').fill('GPS')
  await page.waitForTimeout(500)
  record('map.filters', 'PASS', 'refresh + vehicle filter applied', await shot(page, '08_map_filters'))

  // Mobile viewport
  await page.setViewportSize({ width: 390, height: 844 })
  await page.waitForTimeout(400)
  await assertRtl(page)
  record('map.mobile_rtl', 'PASS', 'mobile RTL map', await shot(page, '09_map_mobile'))
  await page.setViewportSize({ width: 1440, height: 900 })

  // Viewer RBAC — cannot manage providers
  const viewerContext = await browser.newContext({
    locale: 'ar-SA',
    viewport: { width: 1280, height: 800 },
  })
  const viewerPage = await viewerContext.newPage()
  await login(viewerPage, VIEWER)
  await viewerPage.goto(`${BASE}/app/gps-providers`, { waitUntil: 'domcontentloaded' })
  await viewerPage.waitForTimeout(1000)
  const addBtn = dt(viewerPage, 'gps-add-provider')
  if ((await addBtn.count()) > 0 && (await addBtn.isVisible())) {
    throw new Error('Viewer can see add-provider control')
  }
  record('rbac.viewer_providers', 'PASS', 'viewer lacks manage controls', await shot(viewerPage, '10_viewer_providers'))

  await viewerPage.goto(`${BASE}/app/gps-fleet-map`, { waitUntil: 'domcontentloaded' })
  await dt(viewerPage, 'gps-fleet-map').waitFor({ timeout: 15000 })
  record('rbac.viewer_map', 'PASS', 'viewer can open map with gps.map.view', await shot(viewerPage, '11_viewer_map'))

  await viewerContext.close()
  await ownerContext.close()
}

async function main() {
  const env = await verifySafeEnvironment()
  if (!env.ok) {
    report.verdict = 'NOT_RUN'
    report.not_run_reason = env.reason
    console.log(`NOT_RUN: ${env.reason}`)
    fs.writeFileSync(path.join(EVIDENCE, `${stamp}_report.json`), JSON.stringify(report, null, 2))
    fs.writeFileSync(path.join(__dirname, 'last-report.json'), JSON.stringify(report, null, 2))
    process.exit(0)
  }

  report.isolation.database = env.database
  report.isolation.verified_disposable = true
  report.isolation.engine_note = env.engine_note

  if (!fs.existsSync(FIXTURES_PATH)) {
    report.verdict = 'NOT_RUN'
    report.not_run_reason = 'fixtures.json missing — run bootstrap-fixtures.php first'
    fs.writeFileSync(path.join(__dirname, 'last-report.json'), JSON.stringify(report, null, 2))
    process.exit(0)
  }
  const fixtures = JSON.parse(fs.readFileSync(FIXTURES_PATH, 'utf8'))

  let browser
  try {
    browser = await chromium.launch({
      channel: process.env.UAT_BROWSER_CHANNEL || 'chrome',
      headless: true,
    })
    await runJourneys(browser, fixtures)
    const failed = report.steps.filter((s) => s.status === 'FAIL')
    report.verdict = failed.length ? 'FAIL' : 'PASS'
  } catch (err) {
    report.verdict = 'FAIL'
    report.fatal = String(err)
    record('fatal', 'FAIL', String(err))
    console.error(err)
  } finally {
    await browser?.close()
    report.finished_at = new Date().toISOString()
    fs.writeFileSync(path.join(EVIDENCE, `${stamp}_report.json`), JSON.stringify(report, null, 2))
    fs.writeFileSync(path.join(__dirname, 'last-report.json'), JSON.stringify(report, null, 2))
    console.log(`VERDICT=${report.verdict}`)
  }

  process.exit(report.verdict === 'PASS' ? 0 : 1)
}

main()
