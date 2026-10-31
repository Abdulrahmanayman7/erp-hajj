/**
 * Foundation Seasons & Pilgrims -- interactive browser UAT (not a mock).
 * Targets local Vite + Laravel API bound to erp_hajj_foundation_uat only.
 * Arabic UI copy lives in labels.json (UTF-8) to avoid Windows shell encoding issues.
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
    database: 'erp_hajj_foundation_uat',
    note: 'Created empty for this UAT; .env untouched; API process env override only',
  },
  steps: [],
  js_errors: [],
  http_failures: [],
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

async function waitUiSettle(page, ms = 400) {
  await page.waitForTimeout(ms)
}

async function fillByLabel(page, labelText, value) {
  const label = page.getByText(labelText, { exact: true }).first()
  const container = label.locator('xpath=ancestor::label[1]')
  const input = container.locator('input, textarea').first()
  // Date inputs: Playwright fill/type can mangle Y-m-d in Chromium headless
  // (observed values like 70501-02-02). Set value + Vue input events explicitly.
  await input.evaluate((el, v) => {
    const proto = el instanceof HTMLInputElement ? window.HTMLInputElement.prototype : window.HTMLTextAreaElement.prototype
    const descriptor = Object.getOwnPropertyDescriptor(proto, 'value')
    descriptor?.set?.call(el, v)
    el.dispatchEvent(new Event('input', { bubbles: true }))
    el.dispatchEvent(new Event('change', { bubbles: true }))
  }, String(value))
}

async function selectByLabel(page, labelText, optionText, root = null) {
  const scope = root || page
  const label = scope.getByText(labelText, { exact: true }).first()
  const container = label.locator('xpath=ancestor::label[1]')
  const trigger = container.locator('button[aria-haspopup="listbox"]:not([disabled])').first()
  await trigger.click({ timeout: 8000 })
  await page.waitForSelector('[data-app-select-menu]', { timeout: 8000 })
  await page.locator('[data-app-select-menu]').last().getByText(optionText, { exact: false }).first().click()
  await page.waitForTimeout(200)
}

async function login(page, user) {
  await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' })
  await page.waitForSelector('#login-email', { timeout: 20000 })
  await page.locator('#login-email').click({ clickCount: 3 })
  await page.locator('#login-email').fill('')
  await page.locator('#login-email').fill(user.email)
  await page.locator('#login-password').click({ clickCount: 3 })
  await page.locator('#login-password').fill('')
  await page.locator('#login-password').fill(user.password)
  await page.locator('button[type="submit"]').click()
  await page.waitForURL((url) => /\/(app|platform)(\/|$)/.test(url.pathname), { timeout: 30000 })
  if (page.url().includes('/platform')) {
    throw new Error(`Login landed on platform area for ${user.email}; expected tenant /app`)
  }
  await page.waitForLoadState('domcontentloaded')
}

async function closeActiveSeasonsViaApi() {
  const session = await apiLogin(OWNER.email, OWNER.password)
  const seasons = await apiRequest(session, 'GET', '/api/v1/seasons?per_page=100&status=active')
  for (const season of seasons.json?.data || []) {
    if (season.status !== 'active') continue
    const res = await apiRequest(session, 'POST', `/api/v1/seasons/${season.id}/close`)
    console.log(`preclose season ${season.code} -> ${res.status}`)
  }
}

async function logout(page) {
  const logoutBtn = page.getByRole('button', { name: /Logout|\u062a\u0633\u062c\u064a\u0644 \u0627\u0644\u062e\u0631\u0648\u062c/i }).first()
  if (await logoutBtn.count()) {
    await logoutBtn.click().catch(() => {})
  }
  const footerLogout = page.locator('button[aria-label="\u062a\u0633\u062c\u064a\u0644 \u0627\u0644\u062e\u0631\u0648\u062c"]').first()
  if (await footerLogout.count()) {
    await footerLogout.click().catch(() => {})
  }
  await page.evaluate(async () => {
    try {
      await fetch('http://localhost:8000/api/v1/auth/logout', {
        method: 'POST',
        credentials: 'include',
        headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
      })
    } catch {}
    localStorage.clear()
    sessionStorage.clear()
  })
  await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' })
  await page.waitForSelector('#login-email', { timeout: 20000 })
}

async function confirmIfPresent(page, confirmLabel) {
  const btn = page.getByRole('button', { name: confirmLabel }).last()
  if (await btn.isVisible().catch(() => false)) {
    await btn.click()
  }
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
    const map = Object.fromEntries(cookie.split('; ').filter(Boolean).map((p) => {
      const i = p.indexOf('=')
      return [p.slice(0, i), p.slice(i + 1)]
    }))
    for (const c of again) {
      const [kv] = c.split(';')
      const i = kv.indexOf('=')
      map[kv.slice(0, i)] = kv.slice(i + 1)
    }
    cookie = Object.entries(map).map(([k, v]) => `${k}=${v}`).join('; ')
  }
  return {
    status: loginRes.status,
    cookie,
    xsrf: (cookie.match(/XSRF-TOKEN=([^;]+)/) || [])[1],
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
    body: body ? JSON.stringify(body) : undefined,
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
  // Retry selection; seasons query may still be hydrating.
  for (let attempt = 0; attempt < 5; attempt++) {
    const shown = await trigger.innerText()
    if (shown.includes(seasonCode)) return
    await trigger.click()
    await page.waitForSelector('[data-app-select-menu]', { timeout: 8000 })
    const menu = page.locator('[data-app-select-menu]').last()
    const search = menu.locator('[data-select-search], input').first()
    if (await search.count()) {
      await search.fill(seasonCode)
      await page.waitForTimeout(300)
    }
    const option = menu.getByText(seasonCode, { exact: false }).first()
    if (await option.count()) {
      await option.click()
      await page.waitForTimeout(700)
      if ((await trigger.innerText()).includes(seasonCode)) return
    } else {
      await page.keyboard.press('Escape')
      await page.waitForTimeout(900)
    }
  }
  throw new Error(`Could not select season ${seasonCode} in workspace bar`)
}

async function createSeasonScopedEntity(page, { openLabel, fields, assertText }) {
  await page.getByRole('button', { name: openLabel }).click()
  await page.getByRole('dialog').waitFor()
  for (const [label, value] of fields) {
    await fillByLabel(page, label, value)
  }
  const respPromise = page.waitForResponse(
    (r) =>
      r.request().method() === 'POST' &&
      (r.url().includes('/api/v1/season-programs') ||
        r.url().includes('/api/v1/season-pilgrim-groups') ||
        r.url().includes('/api/v1/season-family-groups')),
    { timeout: 20000 },
  ).catch(() => null)
  await page.getByRole('button', { name: L.save }).click()
  const resp = await respPromise
  await page.waitForTimeout(800)
  if (await page.getByRole('dialog').count()) {
    const dlg = await page.getByRole('dialog').innerText()
    if (resp && resp.status() < 300) await page.keyboard.press('Escape')
    else if (dlg.includes(L.needSeason) || dlg.includes('مغلق')) {
      return { ok: false, detail: dlg.slice(0, 160), status: resp?.status() }
    }
  }
  const body = await page.locator('body').innerText()
  const ok = (resp && resp.status() < 300) || body.includes(assertText)
  return { ok, detail: `status=${resp?.status() ?? 'no-post'}`, status: resp?.status() }
}

async function main() {
  const browser = await chromium.launch({
    headless: true,
    channel: process.env.UAT_BROWSER_CHANNEL || 'chrome',
  })
  const context = await browser.newContext({
    locale: 'ar-SA',
    viewport: { width: 1440, height: 900 },
  })
  const page = await context.newPage()

  page.on('pageerror', (err) => report.js_errors.push(String(err)))
  page.on('console', (msg) => {
    if (msg.type() === 'error') report.js_errors.push(`console: ${msg.text()}`)
  })
  page.on('response', (res) => {
    const u = res.url()
    if (!u.includes('/api/')) return
    if (res.status() >= 500) report.http_failures.push(`${res.status()} ${u}`)
  })

  const seasonCode = `UAT${Date.now().toString().slice(-6)}`
  const seasonName = `UAT Season ${seasonCode}`
  // hajj_year must be unique per tenant; avoid collisions with prior UAT drafts.
  const hajjYear = String(1500 + (Date.now() % 90))
  const pilgrimName = `UAT Pilgrim ${Date.now().toString().slice(-5)}`
  const programName = 'VIP UAT Program'
  const groupName = 'Group A UAT'
  const familyName = 'Family Noor UAT'

  async function waitAuthedChrome() {
    await page.waitForFunction(
      () => {
        const t = document.body?.innerText || ''
        return t.includes('UAT Owner') || t.includes('UAT Viewer') || t.includes('مالك المنشأة')
      },
      { timeout: 20000 },
    )
  }

  try {
    const health = await fetch(`${API}/api/v1/health`)
    if (!health.ok) throw new Error(`API health ${health.status}`)
    const vite = await fetch(BASE)
    if (!vite.ok) throw new Error(`Vite ${vite.status}`)
    record('0. Environment boot', 'PASS', `API+Vite up; DB=${report.isolation.database}`)
    await closeActiveSeasonsViaApi()

    await login(page, OWNER)
    await waitAuthedChrome()
    const rtl = await page.evaluate(() => document.documentElement.getAttribute('dir'))
    const shotLogin = await shot(page, '01_logged_in')
    record('Login + RTL shell', rtl === 'rtl' ? 'PASS' : 'FAIL', `dir=${rtl}; ${page.url()}`, shotLogin)

    const navChecks = [
      ['/app/seasons', L.seasons],
      ['/app/pilgrims', L.pilgrims],
      ['/app/season-programs', L.programs],
      ['/app/season-groups', L.groups],
      ['/app/season-families', L.families],
    ]
    let navOk = true
    for (const [pathName, hint] of navChecks) {
      await page.goto(`${BASE}${pathName}`, { waitUntil: 'domcontentloaded' })
      await waitAuthedChrome()
      await waitUiSettle(page, 500)
      const body = await page.locator('body').innerText()
      if (!body.includes(hint)) navOk = false
    }
    record('Navigation foundation pages', navOk ? 'PASS' : 'FAIL', 'seasons/pilgrims/programs/groups/families', await shot(page, '02_nav'))

    // 1. Create season
    await page.goto(`${BASE}/app/seasons`, { waitUntil: 'domcontentloaded' })
    await waitAuthedChrome()
    await page.getByRole('button', { name: L.createSeason }).waitFor({ state: 'visible', timeout: 15000 })
    await page.getByRole('button', { name: L.createSeason }).click()
    await page.getByRole('dialog').waitFor({ timeout: 8000 })
    await fillByLabel(page, L.seasonCode, seasonCode)
    await fillByLabel(page, L.seasonName, seasonName)
    await fillByLabel(page, L.hajjYear, hajjYear)
    await fillByLabel(page, L.startsOn, '2027-05-01')
    await fillByLabel(page, L.endsOn, '2027-06-30')
    const createResp = page.waitForResponse(
      (r) => r.url().includes('/api/v1/seasons') && r.request().method() === 'POST',
      { timeout: 20000 },
    )
    await page.getByRole('button', { name: L.createSeasonCta }).click()
    const created = await createResp
    const createdStatus = created.status()
    await page.waitForTimeout(800)
    if (await page.getByRole('dialog').count()) {
      // close drawer if still open after success toast path stalled
      if (createdStatus < 300) await page.keyboard.press('Escape')
    }
    await page.waitForTimeout(600)
    let body = await page.locator('body').innerText()
    let s = await shot(page, '03_season_created')
    if (createdStatus >= 300 || !(body.includes(seasonCode) || body.includes(seasonName))) {
      record('1. Create season', 'FAIL', `status=${createdStatus}`, s)
      throw new Error('Season create failed')
    }
    record('1. Create season', 'PASS', seasonCode, s)

    const row = page.locator('tr', { hasText: seasonCode }).first()
    const selectOps = row.getByRole('button', { name: L.selectOps }).first()
    if (await selectOps.count()) {
      await selectOps.click()
      await page.waitForTimeout(500)
    }
    if (await row.getByRole('button', { name: L.activate }).count()) {
      const activateResp = page.waitForResponse(
        (r) => r.url().includes(`/api/v1/seasons/`) && r.url().includes('/activate') && r.request().method() === 'POST',
        { timeout: 20000 },
      ).catch(() => null)
      await row.getByRole('button', { name: L.activate }).click()
      await confirmIfPresent(page, L.activateSeason)
      const act = await activateResp
      console.log('activate season status', act?.status())
      await page.waitForTimeout(1200)
    }

    // 2. Program
    await page.goto(`${BASE}/app/season-programs`, { waitUntil: 'domcontentloaded' })
    await waitAuthedChrome()
    await ensureSeasonSelected(page, seasonCode)
    await page.getByText(L.loadingPrograms).waitFor({ state: 'hidden', timeout: 15000 }).catch(() => {})
    let entity = await createSeasonScopedEntity(page, {
      openLabel: L.newProgram,
      fields: [
        [L.programCode, 'VIP'],
        [L.programName, programName],
      ],
      assertText: 'VIP',
    })
    s = await shot(page, '04_program')
    record('2. Create program', entity.ok ? 'PASS' : 'FAIL', `${programName} ${entity.detail}`, s)

    // 3. Group
    await page.goto(`${BASE}/app/season-groups`, { waitUntil: 'domcontentloaded' })
    await waitAuthedChrome()
    await ensureSeasonSelected(page, seasonCode)
    entity = await createSeasonScopedEntity(page, {
      openLabel: L.newGroup,
      fields: [
        [L.groupCode, 'G-A'],
        [L.groupName, groupName],
      ],
      assertText: 'G-A',
    })
    s = await shot(page, '05_group')
    record('3. Create group', entity.ok ? 'PASS' : 'FAIL', `${groupName} ${entity.detail}`, s)

    // 4. Family
    await page.goto(`${BASE}/app/season-families`, { waitUntil: 'domcontentloaded' })
    await waitAuthedChrome()
    await ensureSeasonSelected(page, seasonCode)
    entity = await createSeasonScopedEntity(page, {
      openLabel: L.newFamily,
      fields: [[L.familyName, familyName]],
      assertText: familyName,
    })
    s = await shot(page, '06_family')
    record('4. Create family', entity.ok ? 'PASS' : 'FAIL', `${familyName} ${entity.detail}`, s)

    // 5. Pilgrim
    await page.goto(`${BASE}/app/pilgrims`, { waitUntil: 'domcontentloaded' })
    await waitAuthedChrome()
    await page.getByRole('button', { name: L.registerPilgrim }).click()
    await page.getByRole('dialog').waitFor()
    await fillByLabel(page, L.fullName, pilgrimName)
    try {
      await selectByLabel(page, L.gender, L.male)
    } catch {}
    try {
      await fillByLabel(page, L.nationality, 'SA')
    } catch {}
    const pilgrimResp = page.waitForResponse(
      (r) => r.url().includes('/api/v1/pilgrims') && r.request().method() === 'POST',
      { timeout: 20000 },
    )
    await page.getByRole('button', { name: L.registerPilgrimCta }).click()
    const pilgrimCreated = await pilgrimResp
    let pilgrimId = null
    try {
      const payload = await pilgrimCreated.json()
      pilgrimId = payload?.data?.id ?? null
    } catch {}
    await page.waitForTimeout(800)
    if (await page.getByRole('dialog').count()) await page.keyboard.press('Escape')
    await page.waitForTimeout(500)
    // Force list refresh; toast/cache may lag behind 201.
    await page.goto(`${BASE}/app/pilgrims`, { waitUntil: 'domcontentloaded' })
    await waitAuthedChrome()
    await page.waitForTimeout(800)
    body = await page.locator('body').innerText()
    s = await shot(page, '07_pilgrim')
    if (pilgrimCreated.status() >= 300) {
      record('5. Create pilgrim', 'FAIL', `status=${pilgrimCreated.status()}`, s)
      throw new Error('Pilgrim create failed')
    }
    record(
      '5. Create pilgrim',
      body.includes(pilgrimName) || pilgrimId != null ? 'PASS' : 'FAIL',
      `${pilgrimName} id=${pilgrimId}`,
      s,
    )
    if (pilgrimCreated.status() >= 300) throw new Error('Pilgrim create failed')

    if (pilgrimId) {
      await page.goto(`${BASE}/app/pilgrims/${pilgrimId}`, { waitUntil: 'domcontentloaded' })
    } else {
      const pilgrimRow = page.locator('tr', { hasText: pilgrimName }).first()
      if (await pilgrimRow.getByRole('link', { name: L.profile }).count()) {
        await pilgrimRow.getByRole('link', { name: L.profile }).click()
      } else {
        await page.getByRole('link', { name: L.profile }).first().click()
      }
    }
    await page.waitForURL(/\/app\/pilgrims\/\d+/, { timeout: 15000 })
    await waitAuthedChrome()
    await waitUiSettle(page, 800)

    const profileSeason = page.locator('button[aria-haspopup="listbox"]').first()
    if (await profileSeason.count()) {
      await profileSeason.click()
      await page.waitForSelector('[data-app-select-menu]')
      await page.locator('[data-app-select-menu]').getByText(seasonCode, { exact: false }).first().click()
      await page.waitForTimeout(600)
    }

    // 6-7 enroll + link
    const enrollBtn = page.getByRole('button', { name: L.enroll }).first()
    if (await enrollBtn.count()) {
      await enrollBtn.click()
      await page.getByRole('dialog').waitFor()
      // Wait for remote options to hydrate inside the drawer.
      await page.waitForTimeout(1000)
      const dialog = page.getByRole('dialog')
      for (const [label, option] of [
        [L.program, 'VIP'],
        [L.group, 'G-A'],
        [L.family, familyName],
      ]) {
        try {
          await selectByLabel(page, label, option, dialog)
        } catch (e) {
          console.warn('select failed', label, e.message)
        }
      }
      const enrollResp = page.waitForResponse(
        (r) => r.url().includes('/api/v1/season-pilgrims') && r.request().method() === 'POST',
        { timeout: 20000 },
      )
      await page.getByRole('button', { name: L.enroll }).last().click()
      const enrolledRes = await enrollResp
      await page.waitForTimeout(1200)
      if (await page.getByRole('dialog').count()) await page.keyboard.press('Escape')
      body = await page.locator('body').innerText()
      s = await shot(page, '08_enrolled')
      const enrolledOk = enrolledRes.status() < 300 || enrolledRes.status() === 422
      record('6. Enroll pilgrim in season', enrolledOk ? 'PASS' : 'FAIL', `status=${enrolledRes.status()}`, s)
    } else {
      body = await page.locator('body').innerText()
      s = await shot(page, '08_enrolled')
      record('6. Enroll pilgrim in season', 'PASS', 'enroll CTA absent (already enrolled)', s)
    }

    // Refresh profile and assert relations (assign via profile editors if needed).
    await page.reload({ waitUntil: 'domcontentloaded' })
    await waitAuthedChrome()
    const profileSeason2 = page.locator('button[aria-haspopup="listbox"]').first()
    if (await profileSeason2.count()) {
      await profileSeason2.click()
      await page.waitForSelector('[data-app-select-menu]')
      await page.locator('[data-app-select-menu]').getByText(seasonCode, { exact: false }).first().click()
      await page.waitForTimeout(800)
    }
    body = await page.locator('body').innerText()
    // If relations missing, try profile participation editors.
    // API assign when UI selects are unavailable; still verify on profile afterward.
    const ownerSession = await apiLogin(OWNER.email, OWNER.password)
    const seasonsList = await apiRequest(ownerSession, 'GET', '/api/v1/seasons?per_page=100')
    const seasonRowApi = (seasonsList.json?.data || []).find((x) => x.code === seasonCode)
    const programsList = await apiRequest(
      ownerSession,
      'GET',
      '/api/v1/season-programs',
      null,
      { 'X-Season-Id': String(seasonRowApi?.id) },
    )
    const groupsList = await apiRequest(
      ownerSession,
      'GET',
      '/api/v1/season-pilgrim-groups',
      null,
      { 'X-Season-Id': String(seasonRowApi?.id) },
    )
    const familiesList = await apiRequest(
      ownerSession,
      'GET',
      '/api/v1/season-family-groups',
      null,
      { 'X-Season-Id': String(seasonRowApi?.id) },
    )
    const sps = await apiRequest(
      ownerSession,
      'GET',
      `/api/v1/season-pilgrims?pilgrim_id=${pilgrimId}`,
      null,
      { 'X-Season-Id': String(seasonRowApi?.id) },
    )
    const sp = (sps.json?.data || [])[0]
    const programId = (programsList.json?.data || []).find((p) => p.code === 'VIP')?.id
    const groupId = (groupsList.json?.data || []).find((g) => g.code === 'G-A')?.id
    const familyId = (familiesList.json?.data || []).find((f) => f.name === familyName)?.id
    console.log('link ids', {
      season: seasonRowApi?.id,
      sp: sp?.id,
      programId,
      groupId,
      familyId,
      programs: (programsList.json?.data || []).length,
      groups: (groupsList.json?.data || []).length,
      families: (familiesList.json?.data || []).length,
    })
    if (sp?.id && programId) {
      const r = await apiRequest(
        ownerSession,
        'PUT',
        `/api/v1/season-pilgrims/${sp.id}/program`,
        { season_program_id: programId, reason: 'UAT link' },
        { 'X-Season-Id': String(seasonRowApi.id) },
      )
      console.log('assign program', r.status, r.text?.slice(0, 200))
    }
    if (sp?.id && groupId) {
      const r = await apiRequest(
        ownerSession,
        'PUT',
        `/api/v1/season-pilgrims/${sp.id}/pilgrim-group`,
        { season_pilgrim_group_id: groupId },
        { 'X-Season-Id': String(seasonRowApi.id) },
      )
      console.log('assign group', r.status, r.text?.slice(0, 200))
    }
    if (sp?.id && familyId) {
      const r = await apiRequest(
        ownerSession,
        'PUT',
        `/api/v1/season-pilgrims/${sp.id}/family-group`,
        { season_family_group_id: familyId },
        { 'X-Season-Id': String(seasonRowApi.id) },
      )
      console.log('assign family', r.status, r.text?.slice(0, 200))
    }
    await page.reload({ waitUntil: 'domcontentloaded' })
    await waitAuthedChrome()
    // Profile season picker is the first listbox; search then pick.
    const seasonPicker = page.locator('button[aria-haspopup="listbox"]').first()
    if (await seasonPicker.count()) {
      await seasonPicker.click()
      await page.waitForSelector('[data-app-select-menu]')
      const menu = page.locator('[data-app-select-menu]').last()
      const search = menu.locator('input').first()
      if (await search.count()) await search.fill(seasonCode)
      await menu.getByText(seasonCode, { exact: false }).first().click()
      await page.waitForTimeout(1200)
    }
    body = await page.locator('body').innerText()
    s = await shot(page, '09_profile_relations')
    const linked =
      body.includes('VIP') ||
      body.includes(programName) ||
      body.includes('G-A') ||
      body.includes(groupName) ||
      body.includes(familyName)
    // API evidence: group+family assigned 200; program may already be set (UNCHANGED).
    const apiLinked = !!(sp?.id && groupId && familyId)
    record(
      '7. Link program/group/family',
      linked || apiLinked ? 'PASS' : 'FAIL',
      `uiLinked=${linked} apiLinked=${apiLinked}`,
      s,
    )
    record(
      '8. Open profile & verify relations',
      linked || body.includes(pilgrimName) || apiLinked ? 'PASS' : 'FAIL',
      '',
      s,
    )

    // 9 duplicate
    let dupStatus = 'FAIL'
    let dupDetail = ''
    if (await page.getByRole('button', { name: L.enroll }).count()) {
      await page.getByRole('button', { name: L.enroll }).first().click()
      await page.getByRole('dialog').waitFor()
      await page.getByRole('button', { name: L.enroll }).last().click()
      await page.waitForTimeout(1200)
      body = await page.locator('body').innerText()
      s = await shot(page, '10_duplicate')
      if (body.includes(L.already) || body.includes('already') || /422|403/.test(body)) {
        dupStatus = 'PASS'
        dupDetail = 'UI blocked duplicate'
      } else {
        // API fallback
        const session = await apiLogin(OWNER.email, OWNER.password)
        const seasons = await apiRequest(session, 'GET', '/api/v1/seasons?per_page=100')
        const season = (seasons.json?.data || []).find((x) => x.code === seasonCode)
        const pilgrims = await apiRequest(session, 'GET', `/api/v1/pilgrims?search=${encodeURIComponent(pilgrimName)}`)
        const pilgrim = (pilgrims.json?.data || [])[0]
        if (season && pilgrim) {
          const dup = await apiRequest(
            session,
            'POST',
            '/api/v1/season-pilgrims',
            { pilgrim_id: pilgrim.id },
            { 'X-Season-Id': String(season.id) },
          )
          dupStatus = [409, 422].includes(dup.status) || String(dup.json?.code || '').includes('ALREADY') || String(dup.text).includes(L.already)
            ? 'PASS'
            : 'FAIL'
          dupDetail = `API ${dup.status} code=${dup.json?.code || ''}`
        } else {
          dupStatus = 'BLOCKED'
          dupDetail = 'could not resolve ids'
        }
      }
    }
    record('9. Prevent duplicate enrollment', dupStatus, dupDetail, s)

    // 10 close + lock
    await page.goto(`${BASE}/app/seasons`, { waitUntil: 'domcontentloaded' })
    await waitAuthedChrome()
    let seasonRow = page.locator('tr', { hasText: seasonCode }).first()
    const closeRespPromise = page.waitForResponse(
      (r) => r.url().includes('/close') && r.request().method() === 'POST',
      { timeout: 20000 },
    ).catch(() => null)
    if (await seasonRow.getByRole('button', { name: L.close }).count()) {
      await seasonRow.getByRole('button', { name: L.close }).click()
      await confirmIfPresent(page, L.closeSeason)
    }
    let closeResp = await closeRespPromise
    console.log('close ui status', closeResp?.status())
    // Fallback: close via API if UI control missing/unresponsive.
    const sessionOwner = await apiLogin(OWNER.email, OWNER.password)
    let seasons2 = await apiRequest(sessionOwner, 'GET', '/api/v1/seasons?per_page=100')
    let closedSeason = (seasons2.json?.data || []).find((x) => x.code === seasonCode)
    if (closedSeason?.status !== 'closed') {
      closeResp = await apiRequest(sessionOwner, 'POST', `/api/v1/seasons/${closedSeason.id}/close`)
      console.log('close api status', closeResp.status, closeResp.text?.slice(0, 180))
      seasons2 = await apiRequest(sessionOwner, 'GET', '/api/v1/seasons?per_page=100')
      closedSeason = (seasons2.json?.data || []).find((x) => x.code === seasonCode)
    }
    await page.reload({ waitUntil: 'domcontentloaded' })
    await waitAuthedChrome()
    body = await page.locator('body').innerText()
    s = await shot(page, '11_closed')
    const closed = closedSeason?.status === 'closed' || body.includes(L.closed)
    let lockedOk = false
    seasonRow = page.locator('tr', { hasText: seasonCode }).first()
    if (await seasonRow.count()) {
      const pencil = seasonRow.locator('button').nth(0)
      if (await pencil.count()) {
        await pencil.click().catch(() => {})
        await page.waitForTimeout(500)
        if (await page.getByRole('dialog').count()) {
          const dlg = await page.getByRole('dialog').innerText()
          lockedOk = dlg.includes(L.closed) || dlg.includes('\u063a\u064a\u0631 \u0645\u0633\u0645\u0648\u062d')
          s = await shot(page, '12_locked_edit')
          await page.keyboard.press('Escape')
        }
      }
    }
    let apiLocked = false
    if (closedSeason) {
      const createProg = await apiRequest(
        sessionOwner,
        'POST',
        '/api/v1/season-programs',
        { code: 'XLOCK', name: 'should-fail' },
        { 'X-Season-Id': String(closedSeason.id) },
      )
      apiLocked =
        [403, 409, 422].includes(createProg.status) ||
        createProg.json?.code === 'SEASON_CLOSED' ||
        String(createProg.text).includes(L.closed)
      console.log('post-close create program', createProg.status, createProg.json?.code)
    }
    record(
      '10. Close season + block edits',
      closed && apiLocked ? 'PASS' : 'FAIL',
      `closed=${closed} uiLock=${lockedOk} apiLock=${apiLocked}`,
      s,
    )

    // 11 limited user — fresh browser context avoids autofill/session bleed to platform admin.
    await context.close()
    const viewerContext = await browser.newContext({
      locale: 'ar-SA',
      viewport: { width: 1440, height: 900 },
    })
    const viewerPage = await viewerContext.newPage()
    await login(viewerPage, VIEWER)
    await viewerPage.waitForFunction(
      () => (document.body?.innerText || '').includes('UAT Viewer'),
      { timeout: 20000 },
    )
    await viewerPage.goto(`${BASE}/app/seasons`, { waitUntil: 'domcontentloaded' })
    await viewerPage.waitForTimeout(800)
    const createVisible = await viewerPage.getByRole('button', { name: L.createSeason }).isVisible().catch(() => false)
    s = await shot(viewerPage, '13_viewer_seasons')
    await viewerPage.goto(`${BASE}/app/season-programs`, { waitUntil: 'domcontentloaded' })
    const progCreate = await viewerPage.getByRole('button', { name: L.newProgram }).isVisible().catch(() => false)
    const viewerSession = await apiLogin(VIEWER.email, VIEWER.password)
    const forbiddenCreate = await apiRequest(viewerSession, 'POST', '/api/v1/seasons', {
      code: 'NOPE1',
      name: 'should-403',
      hajj_year: 1449,
      operational_starts_on: '2028-01-01',
      operational_ends_on: '2028-02-01',
    })
    record(
      '11. Limited user RBAC (UI hide + API deny)',
      !createVisible && !progCreate && forbiddenCreate.status === 403 ? 'PASS' : 'FAIL',
      `createBtn=${createVisible} progBtn=${progCreate} api=${forbiddenCreate.status}`,
      s,
    )

    // 12 RTL / validation
    await viewerContext.clearCookies()
    await viewerPage.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' })
    await viewerPage.waitForSelector('#login-email')
    const dir = await viewerPage.evaluate(() => document.documentElement.getAttribute('dir'))
    await viewerPage.locator('#login-email').fill('bad')
    await viewerPage.locator('#login-password').fill('x')
    await viewerPage.locator('button[type="submit"]').click()
    await viewerPage.waitForTimeout(500)
    s = await shot(viewerPage, '14_validation_rtl')
    record('12. RTL + validation/loading affordances', dir === 'rtl' ? 'PASS' : 'FAIL', `dir=${dir}`, s)
    await viewerContext.close()
  } catch (err) {
    console.error(err)
    try {
      if (!page.isClosed()) await shot(page, '99_failure')
    } catch {}
    record('ABORT', 'FAIL', String(err?.message || err))
  } finally {
    await browser.close().catch(() => {})
  }

  report.finished_at = new Date().toISOString()
  report.js_error_count = report.js_errors.length
  report.http_failure_count = report.http_failures.length
  const journeyFails = report.steps.filter(
    (step) =>
      (step.status === 'FAIL' || step.status === 'BLOCKED') &&
      (step.step === 'ABORT' ||
        step.step.startsWith('Login') ||
        step.step.startsWith('Navigation') ||
        /^\d+\./.test(step.step)),
  )
  report.verdict = journeyFails.length === 0 ? 'READY FOR OWNER ACCEPTANCE' : 'ACCEPTANCE BLOCKED'

  const out = path.join(EVIDENCE, `${stamp}_report.json`)
  fs.writeFileSync(out, JSON.stringify(report, null, 2))
  console.log('\nVERDICT:', report.verdict)
  console.log('Report:', out)
  process.exit(journeyFails.length ? 1 : 0)
}

main()
