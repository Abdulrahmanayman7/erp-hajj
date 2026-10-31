/**
 * S01 Fleet & Registry — interactive browser UAT (not mocks).
 * Targets Vite + Laravel API bound to erp_hajj_s01_uat only.
 */
import { chromium } from 'playwright-core'
import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const EVIDENCE = path.join(__dirname, 'evidence')
const L = JSON.parse(fs.readFileSync(path.join(__dirname, 'labels.json'), 'utf8'))
const BASE = process.env.UAT_BASE_URL || 'http://localhost:5173'
const API = process.env.UAT_API_URL || 'http://127.0.0.1:8000'
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
  started_at: new Date().toISOString(),
  isolation: {
    database: 'erp_hajj_s01_uat',
    note: 'Dedicated empty schema; .env untouched; API process env override only',
  },
  gps: {
    live_integration: 'OUT OF SCOPE for S01 acceptance',
    readiness: 'No Backend GPS fields — UI notice only (ADR-0018 / ADR-0021)',
  },
  steps: [],
  js_errors: [],
  http_failures: [],
  fixtures: {},
  verdict: 'ACCEPTANCE BLOCKED',
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

async function fillByLabel(page, labelText, value) {
  const label = page.getByText(labelText, { exact: true }).first()
  const container = label.locator('xpath=ancestor::*[self::label or self::div][1]')
  const input = container.locator('input, textarea').first()
  await input.evaluate((el, v) => {
    const proto =
      el instanceof HTMLInputElement
        ? window.HTMLInputElement.prototype
        : window.HTMLTextAreaElement.prototype
    const descriptor = Object.getOwnPropertyDescriptor(proto, 'value')
    descriptor?.set?.call(el, v)
    el.dispatchEvent(new Event('input', { bubbles: true }))
    el.dispatchEvent(new Event('change', { bubbles: true }))
  }, String(value))
}

async function selectByLabel(page, labelText, optionText) {
  const label = page.getByText(labelText, { exact: true }).first()
  const container = label.locator('xpath=ancestor::*[self::label or self::div][1]')
  const trigger = container.locator('button[aria-haspopup="listbox"]:not([disabled])').first()
  await trigger.click({ timeout: 8000 })
  await page.waitForSelector('[data-app-select-menu]', { timeout: 8000 })
  await page.locator('[data-app-select-menu]').last().getByText(optionText, { exact: false }).first().click()
  await page.waitForTimeout(200)
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

async function logout(page) {
  await page.evaluate(async () => {
    try {
      await fetch('http://127.0.0.1:8000/api/v1/auth/logout', {
        method: 'POST',
        credentials: 'include',
        headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
      })
    } catch {}
    localStorage.clear()
    sessionStorage.clear()
  })
  await page.context().clearCookies()
  await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' }).catch(async () => {
    await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' })
  })
  await page.waitForSelector('#login-email', { timeout: 20000 })
}

async function apiLogin(email, password) {
  const csrfRes = await fetch(`${API}/sanctum/csrf-cookie`, {
    headers: { Origin: BASE, Referer: `${BASE}/` },
  })
  const setCookie = csrfRes.headers.getSetCookie?.() || []
  let cookie = setCookie.map((c) => c.split(';')[0]).join('; ')
  const xsrf = (cookie.match(/XSRF-TOKEN=([^;]+)/) || [])[1]
  const loginRes = await fetch(`${API}/api/v1/auth/login`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      Origin: BASE,
      Referer: `${BASE}/`,
      Cookie: cookie,
      'X-XSRF-TOKEN': xsrf ? decodeURIComponent(xsrf) : '',
      'X-Requested-With': 'XMLHttpRequest',
    },
    body: JSON.stringify({ email, password }),
  })
  const again = loginRes.headers.getSetCookie?.() || []
  if (again.length) {
    const map = Object.fromEntries(
      cookie
        .split('; ')
        .filter(Boolean)
        .map((p) => {
          const i = p.indexOf('=')
          return [p.slice(0, i), p.slice(i + 1)]
        }),
    )
    for (const c of again) {
      const [kv] = c.split(';')
      const i = kv.indexOf('=')
      map[kv.slice(0, i)] = kv.slice(i + 1)
    }
    cookie = Object.entries(map)
      .map(([k, v]) => `${k}=${v}`)
      .join('; ')
  }
  return {
    status: loginRes.status,
    cookie,
    xsrf: (cookie.match(/XSRF-TOKEN=([^;]+)/) || [])[1],
    json: await loginRes.json().catch(() => null),
  }
}

async function apiRequest(session, method, urlPath, body, headers = {}) {
  const res = await fetch(`${API}${urlPath}`, {
    method,
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      Origin: BASE,
      Referer: `${BASE}/`,
      Cookie: session.cookie,
      'X-XSRF-TOKEN': session.xsrf ? decodeURIComponent(session.xsrf) : '',
      'X-Requested-With': 'XMLHttpRequest',
      ...headers,
    },
    body: body === undefined ? undefined : JSON.stringify(body),
  })
  const text = await res.text()
  let json = null
  try {
    json = JSON.parse(text)
  } catch {}
  return { status: res.status, json, text }
}

async function ensureSeasonSelected(page, seasonCode) {
  const bar = page.locator('[aria-label="season-workspace"]')
  await bar.waitFor({ state: 'visible', timeout: 15000 })
  const trigger = bar.locator('button[aria-haspopup="listbox"]').first()
  for (let attempt = 0; attempt < 5; attempt++) {
    const shown = await trigger.innerText()
    if (shown.includes(seasonCode)) return
    await trigger.click()
    await page.waitForSelector('[data-app-select-menu]', { timeout: 8000 })
    const menu = page.locator('[data-app-select-menu]').last()
    const option = menu.getByText(seasonCode, { exact: false }).first()
    if (await option.count()) {
      await option.click()
      await page.waitForTimeout(400)
      return
    }
    await page.keyboard.press('Escape').catch(() => {})
    await page.waitForTimeout(500)
  }
  throw new Error(`Could not select season ${seasonCode}`)
}

async function ensurePlatformSetupClosed() {
  const statusRes = await fetch(`${API}/api/v1/platform/setup/status`, {
    headers: { Accept: 'application/json', Origin: BASE, Referer: `${BASE}/` },
  })
  const statusJson = await statusRes.json().catch(() => null)
  if (statusJson?.data?.available !== true) return
  const csrfRes = await fetch(`${API}/sanctum/csrf-cookie`, {
    headers: { Origin: BASE, Referer: `${BASE}/` },
  })
  const setCookie = csrfRes.headers.getSetCookie?.() || []
  const cookie = setCookie.map((c) => c.split(';')[0]).join('; ')
  const xsrf = (cookie.match(/XSRF-TOKEN=([^;]+)/) || [])[1]
  const setupRes = await fetch(`${API}/api/v1/platform/setup`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      Origin: BASE,
      Referer: `${BASE}/`,
      Cookie: cookie,
      'X-XSRF-TOKEN': xsrf ? decodeURIComponent(xsrf) : '',
      'X-Requested-With': 'XMLHttpRequest',
    },
    body: JSON.stringify({
      name: 'Platform Admin',
      email: 'platform.admin@example.com',
      password: 'Admin@12345',
      password_confirmation: 'Admin@12345',
    }),
  })
  if (![200, 201, 422].includes(setupRes.status)) {
    throw new Error(`platform setup failed ${setupRes.status}`)
  }
}

async function prepareFixtures() {
  await ensurePlatformSetupClosed()
  const session = await apiLogin(OWNER.email, OWNER.password)
  if (session.status !== 200) throw new Error(`API login failed: ${session.status}`)

  const year = 1500 + Math.floor(Math.random() * 80)
  const seasonCode = `S01${year}`
  const seasonRes = await apiRequest(session, 'POST', '/api/v1/seasons', {
    code: seasonCode,
    name: `موسم قبول الأسطول ${year}`,
    hajj_year: year,
    operational_starts_on: '2027-05-01',
    operational_ends_on: '2027-06-30',
  })
  let season = seasonRes.json?.data
  if (!season?.id) throw new Error(`season create failed: ${seasonRes.status} ${seasonRes.text}`)
  // Only one Active season is allowed — close any existing Active before activating.
  const listed = await apiRequest(session, 'GET', '/api/v1/seasons?per_page=100')
  for (const row of listed.json?.data || []) {
    if (row.status === 'active' && row.id !== season.id) {
      const closeOther = await apiRequest(session, 'POST', `/api/v1/seasons/${row.id}/close`)
      if (closeOther.status !== 200) {
        throw new Error(
          `close prior active season ${row.id} failed: ${closeOther.status} ${closeOther.text}`,
        )
      }
    }
  }
  const act = await apiRequest(session, 'POST', `/api/v1/seasons/${season.id}/activate`)
  if (act.status !== 200) {
    throw new Error(`season activate failed ${act.status} ${act.text}`)
  }
  season = act.json?.data || season
  season.code = season.code || seasonCode

  const unit = await apiRequest(session, 'POST', '/api/v1/organization-units', {
    name: 'وحدة أسطول UAT',
    code: 'S01FLEET',
    type: 'department',
  })
  if (unit.status !== 201 && unit.status !== 422) {
    throw new Error(`org unit failed ${unit.status} ${unit.text}`)
  }
  let unitId = unit.json?.data?.id
  if (!unitId) {
    const units = await apiRequest(session, 'GET', '/api/v1/organization-units?per_page=100')
    unitId = (units.json?.data || []).find((u) => u.code === 'S01FLEET')?.id
  }
  if (!unitId) throw new Error(`org unit id missing ${unit.status} ${unit.text}`)

  const stamp = `${year}-${Date.now().toString().slice(-5)}`
  const employeeName = `سائق UAT ${stamp}`
  const assetName = `أصل مركبة UAT ${stamp}`
  const contractTitle = `عقد نقل UAT ${stamp}`

  const employee = await apiRequest(session, 'POST', '/api/v1/employees', {
    full_name: employeeName,
    organization_unit_id: unitId,
  })
  if (employee.status !== 201) throw new Error(`employee failed ${employee.status} ${employee.text}`)

  const asset = await apiRequest(session, 'POST', '/api/v1/assets', {
    name: assetName,
  })
  if (asset.status !== 201) throw new Error(`asset failed ${asset.status} ${asset.text}`)

  const category = await apiRequest(session, 'POST', '/api/v1/contract-categories', {
    name: 'فئة عقود النقل UAT',
    code: 'TR-UAT',
  })
  let categoryId = category.json?.data?.id
  if (category.status === 422) {
    const cats = await apiRequest(session, 'GET', '/api/v1/contract-categories?per_page=100')
    categoryId = (cats.json?.data || []).find((c) => c.code === 'TR-UAT' || c.name.includes('النقل'))?.id
  }
  if (!categoryId) throw new Error(`contract category failed ${category.status} ${category.text}`)

  const contract = await apiRequest(session, 'POST', '/api/v1/contracts', {
    title: contractTitle,
    contract_category_id: categoryId,
    counterparty_name: 'مورد النقل',
    start_date: '2027-01-01',
  })
  if (contract.status !== 201) throw new Error(`contract failed ${contract.status} ${contract.text}`)

  report.fixtures = {
    season_id: season.id,
    season_code: season.code || 'S01UAT',
    employee_id: employee.json.data.id,
    asset_id: asset.json.data.id,
    contract_id: contract.json.data.id,
    employee_name: employeeName,
    asset_name: assetName,
    contract_title: contractTitle,
  }
  return { session, ...report.fixtures }
}

async function main() {
  let browser
  try {
    const health = await fetch(`${API}/api/v1/health`)
    if (!health.ok) throw new Error('API health failed')
    const vite = await fetch(BASE)
    if (!vite.ok) throw new Error('Vite failed')
    record('0. Environment boot', 'PASS', `API ${API} + Vite ${BASE}`)

    const fixtures = await prepareFixtures()
    record('0b. API fixtures', 'PASS', JSON.stringify(report.fixtures))

    browser = await chromium.launch({
      channel: 'chrome',
      headless: true,
    })
    const context = await browser.newContext({
      locale: 'ar-SA',
      viewport: { width: 1440, height: 900 },
    })
    const page = await context.newPage()
    page.on('pageerror', (err) => report.js_errors.push(String(err)))
    page.on('response', (res) => {
      if (res.url().includes('/api/') && res.status() >= 500) {
        report.http_failures.push(`${res.status()} ${res.url()}`)
      }
    })

    // 1. Login
    await login(page, OWNER)
    const rtl = await page.evaluate(() => document.documentElement.getAttribute('dir'))
    record('1. Login + RTL shell', rtl === 'rtl' ? 'PASS' : 'FAIL', `dir=${rtl}`, await shot(page, '01_logged_in'))

    // 2. Vehicles page
    await page.goto(`${BASE}/app/transport-vehicles`, { waitUntil: 'domcontentloaded' })
    await page.getByRole('heading', { name: L.navVehicles }).waitFor({ timeout: 15000 })
    const gpsVisible = await page.getByText(L.gpsTitle).isVisible()
    const fakeMapWidgets = await page.locator('[data-gps-live-map], .leaflet-container, #map, canvas.map').count()
    const fakeProviderUi = await page.getByText(/مزود GPS|GPS Provider|Live Tracking Map/i).count()
    record(
      '2. Open vehicles + GPS notice (no live map)',
      gpsVisible && fakeMapWidgets === 0 && fakeProviderUi === 0 ? 'PASS' : 'FAIL',
      `gpsNotice=${gpsVisible} mapWidgets=${fakeMapWidgets} providerUi=${fakeProviderUi}`,
      await shot(page, '02_vehicles'),
    )

    // 3. Create + edit vehicle
    await page.getByRole('button', { name: L.addVehicle }).click()
    await page.getByText(/مركبة جديدة/).waitFor({ timeout: 8000 })
    const plateA = `S01-${Date.now().toString().slice(-6)}`
    const plateB = `${plateA}A`
    await fillByLabel(page, L.plate, plateA)
    await selectByLabel(page, L.class, L.bus)
    await page.locator('aside[role="dialog"]').getByRole('button', { name: L.save }).click()
    await page.locator('aside[role="dialog"]').waitFor({ state: 'hidden', timeout: 15000 }).catch(async () => {
      await page.locator('.fixed.inset-0.z-50 .absolute.inset-0').first().click({ force: true }).catch(() => {})
    })
    await page.getByText(plateA).first().waitFor({ timeout: 10000 })
    const createdVisible = await page.getByText(plateA).first().isVisible()
    await page.locator('tr', { hasText: plateA }).locator('button').nth(1).click()
    await page.getByText(/تعديل المركبة/).waitFor({ timeout: 8000 })
    await fillByLabel(page, L.plate, plateB)
    await page.locator('aside[role="dialog"]').getByRole('button', { name: L.save }).click()
    await page.locator('aside[role="dialog"]').waitFor({ state: 'hidden', timeout: 15000 }).catch(async () => {
      await page.locator('.fixed.inset-0.z-50 .absolute.inset-0').first().click({ force: true }).catch(() => {})
    })
    await page.getByText(plateB).first().waitFor({ timeout: 10000 })
    const editedVisible = await page.getByText(plateB).first().isVisible()
    report.fixtures.plate = plateB
    record(
      '3. Create + edit vehicle',
      createdVisible && editedVisible ? 'PASS' : 'FAIL',
      `created=${createdVisible} edited=${editedVisible} plate=${plateB}`,
      await shot(page, '03_vehicle_crud'),
    )

    // 4. Search / filter
    await page.locator('input[type="search"]').first().fill(plateB)
    await page.waitForTimeout(700)
    const searchHit = await page.getByText(plateB).count()
    record('4. Search vehicles', searchHit > 0 ? 'PASS' : 'FAIL', `hits=${searchHit}`, await shot(page, '04_search'))

    async function closeDialog() {
      const dialog = page.locator('aside[role="dialog"]')
      if (await dialog.count()) {
        await dialog.waitFor({ state: 'hidden', timeout: 12000 }).catch(async () => {
          await page.locator('.fixed.inset-0.z-50 .absolute.inset-0').first().click({ force: true }).catch(() => {})
          await dialog.waitFor({ state: 'hidden', timeout: 5000 }).catch(() => {})
        })
      }
    }

    // 5. Drivers
    await page.goto(`${BASE}/app/transport-drivers`, { waitUntil: 'domcontentloaded' })
    await page.getByRole('button', { name: L.addDriver }).click()
    await page.getByText(/سائق جديد/).waitFor({ timeout: 8000 })
    await fillByLabel(page, L.fullName, 'سائق أسطول تجريبي')
    await fillByLabel(page, L.phone, '0500000011')
    await page.locator('aside[role="dialog"]').getByRole('button', { name: L.save }).click()
    await closeDialog()
    await page.getByText('سائق أسطول تجريبي').first().waitFor({ timeout: 10000 })
    const driverOk = await page.getByText('سائق أسطول تجريبي').first().isVisible()
    await page.locator('tr', { hasText: 'سائق أسطول تجريبي' }).locator('button').first().click()
    await page.getByText(/تعديل السائق/).waitFor({ timeout: 8000 })
    await fillByLabel(page, L.fullName, 'سائق أسطول محدّث')
    await page.locator('aside[role="dialog"]').getByRole('button', { name: L.save }).click()
    await closeDialog()
    await page.getByText('سائق أسطول محدّث').first().waitFor({ timeout: 10000 })
    const driverEdit = await page.getByText('سائق أسطول محدّث').first().isVisible()
    record(
      '5. Create + edit driver',
      driverOk && driverEdit ? 'PASS' : 'FAIL',
      `create=${driverOk} edit=${driverEdit}`,
      await shot(page, '05_drivers'),
    )

    // 6. Transport point
    await page.goto(`${BASE}/app/transport-points`, { waitUntil: 'domcontentloaded' })
    await page.getByRole('button', { name: L.addPoint }).click()
    await page.getByText(/نقطة نقل جديدة/).waitFor({ timeout: 8000 })
    const pointCode = `JED${Date.now().toString().slice(-5)}`
    await fillByLabel(page, L.pointCode, pointCode)
    await fillByLabel(page, L.pointName, 'مطار جدة UAT')
    await selectByLabel(page, L.pointType, L.airport)
    await page.locator('aside[role="dialog"]').getByRole('button', { name: L.save }).click()
    await closeDialog()
    await page.getByText(pointCode).first().waitFor({ timeout: 10000 })
    const pointOk = await page.getByText(pointCode).first().isVisible()
    record('6. Create transport point', pointOk ? 'PASS' : 'FAIL', pointCode, await shot(page, '06_points'))

    // 7. Asset/contract links
    await page.goto(`${BASE}/app/transport-vehicles`, { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(500)
    const plate = report.fixtures.plate
    await page.locator('tr', { hasText: plate }).locator('button').first().click()
    await page.getByText(L.linksTitle).waitFor({ timeout: 8000 })
    const linksDialog = page.locator('aside[role="dialog"]')
    const linksSave = linksDialog.getByRole('button', { name: L.linksSave })
    await linksSave.waitFor({ state: 'visible', timeout: 8000 })
    await page.waitForFunction(
      (sel) => {
        const btn = document.querySelector(sel)
        return btn instanceof HTMLButtonElement && !btn.disabled
      },
      'aside[role="dialog"] button:not([disabled])',
      { timeout: 15000 },
    ).catch(() => {})
    // Prefer the fixture asset/contract ids (unique) over duplicate display names.
    async function pickRemoteOption(trigger, label) {
      await trigger.click()
      await page.waitForSelector('[data-app-select-menu]', { timeout: 10000 })
      const menu = page.locator('[data-app-select-menu]').last()
      const search = menu.locator('[data-select-search]')
      if (await search.count()) {
        await search.fill(label)
        await page.waitForTimeout(500)
      }
      await menu.getByText(label, { exact: false }).first().waitFor({ timeout: 10000 })
      await menu.getByText(label, { exact: false }).first().click()
      await page.waitForTimeout(250)
    }
    await pickRemoteOption(linksDialog.locator('button[aria-haspopup="listbox"]').first(), fixtures.asset_name)
    await pickRemoteOption(linksDialog.locator('button[aria-haspopup="listbox"]').nth(1), fixtures.contract_title)
    await linksSave.click()
    await linksDialog.waitFor({ state: 'hidden', timeout: 20000 })
    const assetCell = page.locator('tr', { hasText: plate }).locator('td').nth(3)
    const contractCell = page.locator('tr', { hasText: plate }).locator('td').nth(4)
    await assetCell.getByText(`#${fixtures.asset_id}`).waitFor({ timeout: 15000 })
    await contractCell.getByText(`#${fixtures.contract_id}`).waitFor({ timeout: 15000 })
    const linkRow = await page.locator('tr', { hasText: plate }).innerText()
    const assetText = (await assetCell.innerText()).trim()
    const contractText = (await contractCell.innerText()).trim()
    const linkOk =
      assetText === `#${fixtures.asset_id}` && contractText === `#${fixtures.contract_id}`
    record(
      '7. Link vehicle asset/contract',
      linkOk ? 'PASS' : 'FAIL',
      `asset=${assetText} contract=${contractText} | ${linkRow.slice(0, 120)}`,
      await shot(page, '07_links'),
    )
    // 8. Season fleet link
    await page.goto(`${BASE}/app/transport-season-fleet`, { waitUntil: 'domcontentloaded' })
    await ensureSeasonSelected(page, fixtures.season_code)
    await page.getByRole('button', { name: L.addSeasonVehicle }).click()
    await page.getByText(/ربط مركبة بالموسم/).waitFor({ timeout: 8000 })
    await selectByLabel(page, L.vehicle, plate)
    await fillByLabel(page, L.capacity, '45')
    await selectByLabel(page, L.serviceLevel, L.standard)
    await page.locator('aside[role="dialog"]').getByRole('button', { name: L.save }).click()
    await closeDialog()
    await page.getByText(plate).first().waitFor({ timeout: 10000 })
    const fleetOk = await page.getByText(plate).first().isVisible()
    record('8. Link vehicle to season fleet', fleetOk ? 'PASS' : 'FAIL', '', await shot(page, '08_season_fleet'))

    // 9. Default crew (first manage button is crew)
    await page.locator('tr', { hasText: plate }).locator('button').first().click()
    await page.getByText(L.crewTitle).waitFor({ timeout: 8000 })
    await selectByLabel(page, L.driverKind, L.driverEmployeeKind)
    await page.waitForTimeout(300)
    const empTrigger = page.locator('aside[role="dialog"] button[aria-haspopup="listbox"]').nth(1)
    await empTrigger.click()
    await page.waitForSelector('[data-app-select-menu]', { timeout: 8000 })
    await page
      .locator('[data-app-select-menu]')
      .last()
      .getByText(fixtures.employee_name, { exact: false })
      .first()
      .click()
    await page.locator('aside[role="dialog"]').getByRole('button', { name: L.crewSave }).click()
    await closeDialog()
    await page.locator('tr', { hasText: plate }).getByText(fixtures.employee_name).waitFor({ timeout: 10000 })
    const crewOk = await page.locator('tr', { hasText: plate }).getByText(fixtures.employee_name).count()
    record('9. Assign default crew', crewOk > 0 ? 'PASS' : 'FAIL', `crewHits=${crewOk}`, await shot(page, '09_crew'))

    // 10. Conflict: second vehicle cannot take same asset
    const plateConflict = `S01-C-${Date.now().toString().slice(-6)}`
    const v2 = await apiRequest(fixtures.session, 'POST', '/api/v1/transport-vehicles', {
      plate_number: plateConflict,
      vehicle_class: 'bus',
    })
    if (v2.status !== 201) throw new Error(`conflict vehicle create failed ${v2.status} ${v2.text}`)
    const conflict = await apiRequest(fixtures.session, 'PUT', `/api/v1/transport-vehicles/${v2.json.data.id}/links`, {
      asset_id: fixtures.asset_id,
      contract_id: null,
    })
    record(
      '10. Prevent asset conflict',
      conflict.status === 422 && conflict.json?.code === 'TRANSPORT_VEHICLE_ASSET_TAKEN' ? 'PASS' : 'FAIL',
      `${conflict.status} ${conflict.json?.code || ''}`,
    )

    // 11. Close season + block fleet write (domain code is 422 SEASON_CLOSED — not 403)
    const closed = await apiRequest(fixtures.session, 'POST', `/api/v1/seasons/${fixtures.season_id}/close`)
    const blocked = await apiRequest(
      fixtures.session,
      'POST',
      '/api/v1/transport-season-vehicles',
      {
        transport_vehicle_id: v2.json.data.id,
        operational_capacity: 20,
        service_level: 'standard',
      },
      { 'X-Season-Id': String(fixtures.season_id) },
    )
    record(
      '11. Closed season blocks fleet write',
      closed.status === 200 && blocked.status === 422 && blocked.json?.code === 'SEASON_CLOSED'
        ? 'PASS'
        : 'FAIL',
      `close=${closed.status} write=${blocked.status}/${blocked.json?.code}`,
      await shot(page, '11_closed'),
    )
    // 12. Limited user RBAC
    await logout(page)
    await login(page, VIEWER)
    await page.goto(`${BASE}/app/transport-vehicles`, { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(800)
    const addHidden = (await page.getByRole('button', { name: L.addVehicle }).count()) === 0
    const viewerWrite = await apiRequest(await apiLogin(VIEWER.email, VIEWER.password), 'POST', '/api/v1/transport-vehicles', {
      plate_number: 'VIEWER-X',
      vehicle_class: 'car',
    })
    record(
      '12. Limited user RBAC',
      addHidden && viewerWrite.status === 403 ? 'PASS' : 'FAIL',
      `ctaHidden=${addHidden} api=${viewerWrite.status}`,
      await shot(page, '12_viewer'),
    )

    // 13. RTL + validation
    await logout(page)
    await login(page, OWNER)
    await page.goto(`${BASE}/app/transport-points`, { waitUntil: 'domcontentloaded' })
    await page.getByRole('heading', { name: L.navPoints }).waitFor({ timeout: 15000 })
    await page.getByRole('button', { name: L.addPoint }).click()
    await page.locator('aside[role="dialog"]').getByRole('button', { name: L.save }).click()
    await page.waitForTimeout(300)
    const validation = await page.getByText(/هذا الحقل مطلوب|صيغة/).count()
    const dir = await page.evaluate(() => document.documentElement.getAttribute('dir'))
    record(
      '13. RTL + validation errors',
      dir === 'rtl' && validation > 0 ? 'PASS' : 'FAIL',
      `dir=${dir} validationMsgs=${validation}`,
      await shot(page, '13_validation_rtl'),
    )
    // dismiss validation drawer before next navigation
    await page.keyboard.press('Escape').catch(() => {})
    await page.locator('.fixed.inset-0.z-50 .absolute.inset-0').first().click({ force: true }).catch(() => {})
    await page.locator('aside[role="dialog"]').waitFor({ state: 'hidden', timeout: 5000 }).catch(() => {})

    // 14. GPS fake map absent (reconfirm on registry — season-independent)
    await page.goto(`${BASE}/app/transport-vehicles`, { waitUntil: 'domcontentloaded' })
    await page.getByRole('heading', { name: L.navVehicles }).waitFor({ timeout: 20000 })
    await page.getByText(L.gpsTitle).waitFor({ timeout: 10000 })
    const liveWidgets = await page.locator('[data-gps-live-map], .leaflet-container, #map').count()
    const notice = await page.getByText(L.gpsTitle).isVisible()
    record(
      '14. No fake GPS live integration',
      liveWidgets === 0 && notice ? 'PASS' : 'FAIL',
      `liveWidgets=${liveWidgets} notice=${notice}`,
      await shot(page, '14_gps_scope'),
    )
    const fails = report.steps.filter((s) => s.status === 'FAIL')
    report.verdict = fails.length === 0 ? 'READY FOR OWNER ACCEPTANCE' : 'ACCEPTANCE BLOCKED'
    report.finished_at = new Date().toISOString()
    report.js_error_count = report.js_errors.length
    report.http_failure_count = report.http_failures.length
  } catch (error) {
    report.fatal = String(error?.stack || error)
    record('99. Fatal', 'FAIL', String(error))
    console.error(error)
  } finally {
    if (browser) await browser.close()
    const out = path.join(EVIDENCE, `${stamp}_report.json`)
    fs.writeFileSync(out, JSON.stringify(report, null, 2), 'utf8')
    console.log(`REPORT ${out}`)
    console.log(`VERDICT ${report.verdict}`)
    process.exit(report.verdict === 'READY FOR OWNER ACCEPTANCE' ? 0 : 1)
  }
}

main()
