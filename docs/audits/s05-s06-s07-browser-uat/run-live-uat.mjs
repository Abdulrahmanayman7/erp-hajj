/**
 * S05/S06/S07 Live Browser UAT — real API + UI persistence checks.
 * Requires attested disposable DB matching /^erp_hajj_.+_uat$/.
 */
import { chromium } from 'playwright-core'
import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
import {
  ATTESTATION_PATH,
  attestationRequestHeaders,
  evaluateAttestation,
} from '../s12-phase2-browser-uat/uat-attestation.mjs'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const EVIDENCE = path.join(__dirname, 'evidence')
const BASE = process.env.UAT_BASE_URL || 'http://127.0.0.1:5173'
const API = process.env.UAT_API_URL || 'http://127.0.0.1:8000'
const TOKEN =
  process.env.BROWSER_UAT_ATTESTATION_TOKEN || process.env.UAT_ATTESTATION_TOKEN || ''
const OWNER = {
  email: process.env.UAT_OWNER_EMAIL || 'uat.owner@example.com',
  password: process.env.UAT_OWNER_PASSWORD || 'Admin@12345',
}
const VIEWER = {
  email: process.env.UAT_VIEWER_EMAIL || 'uat.viewer@example.com',
  password: process.env.UAT_VIEWER_PASSWORD || 'Viewer@12345',
}

const fixtures = JSON.parse(fs.readFileSync(path.join(__dirname, 'fixtures.json'), 'utf8'))
const stamp = new Date().toISOString().replace(/[:.]/g, '-')
const report = {
  suite: 's05-s06-s07-live-browser-uat',
  started_at: new Date().toISOString(),
  isolation: { database: null, verified_disposable: false, fixtures },
  env: { base: BASE, api: API },
  steps: [],
  modules: {
    s05: { verdict: 'NOT_RUN', notes: [] },
    s06: { verdict: 'NOT_RUN', notes: [] },
    s07: { verdict: 'NOT_RUN', notes: [] },
  },
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

async function probeJson(url, timeoutMs = 5000, headers = {}) {
  const ctrl = new AbortController()
  const timer = setTimeout(() => ctrl.abort(), timeoutMs)
  try {
    const res = await fetch(url, { signal: ctrl.signal, headers, redirect: 'manual' })
    if (res.status >= 300 && res.status < 400) {
      return { ok: false, status: res.status, json: null, error: `Redirect ${res.status}` }
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

async function verifyEnvironment() {
  if (!TOKEN) return { ok: false, reason: 'Missing BROWSER_UAT_ATTESTATION_TOKEN' }
  const health = await probeJson(`${API}/api/v1/health`)
  if (!health.ok && health.status === 0) {
    return { ok: false, reason: `API unreachable at ${API}` }
  }
  const attestationUrl = `${API.replace(/\/$/, '')}${ATTESTATION_PATH}`
  const headers = attestationRequestHeaders(attestationUrl, API, TOKEN)
  const attestation = await probeJson(attestationUrl, 5000, headers)
  const verdict = evaluateAttestation(attestation.json, {
    ok: attestation.ok,
    status: attestation.status,
    expectedDatabase: process.env.UAT_DATABASE || fixtures.database || null,
  })
  if (!verdict.ok) return { ok: false, reason: verdict.reason }
  return { ok: true, database: verdict.database }
}

async function login(page, user) {
  await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' })
  await page.waitForSelector('#login-email', { timeout: 20000 })
  await page.fill('#login-email', user.email)
  await page.fill('#login-password', user.password)
  await page.click('button[type="submit"]')
  await page.waitForURL((url) => /\/(app|platform)(\/|$)/.test(url.pathname), { timeout: 30000 })
  if (page.url().includes('/platform')) throw new Error(`Platform landing for ${user.email}`)
}

async function apiFromPage(page, method, pathName, body = undefined) {
  const seasonId = fixtures?.season_id ?? null
  return page.evaluate(
    async ({ method, pathName, body, api, seasonId }) => {
      const csrf = document.cookie
        .split('; ')
        .find((c) => c.startsWith('XSRF-TOKEN='))
        ?.split('=')
        .slice(1)
        .join('=')
      const headers = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': csrf ? decodeURIComponent(csrf) : '',
      }
      if (seasonId) headers['X-Season-Id'] = String(seasonId)
      if (body !== undefined) headers['Content-Type'] = 'application/json'
      const res = await fetch(`${api}${pathName}`, {
        method,
        credentials: 'include',
        headers,
        body: body !== undefined ? JSON.stringify(body) : undefined,
      })
      const text = await res.text()
      let json = null
      try {
        json = JSON.parse(text)
      } catch {
        json = null
      }
      return { status: res.status, json, text: text.slice(0, 500) }
    },
    { method, pathName, body, api: API, seasonId },
  )
}

async function selectSeason(page, seasonId) {
  await page.evaluate((id) => {
    sessionStorage.setItem('erp-hajj.accommodation.seasonId', String(id))
  }, seasonId)
  // Ensure we are on a season-scoped page with workspace bar.
  if (!/\/app\/(risks|complaints|season-projects)/.test(page.url())) {
    await page.goto(`${BASE}/app/risks`, { waitUntil: 'networkidle' })
  } else {
    await page.reload({ waitUntil: 'networkidle' }).catch(async () => {
      await page.reload({ waitUntil: 'domcontentloaded' })
    })
  }
  await page.waitForTimeout(800)
  const bar = page.locator('section[aria-label="season-workspace"]')
  if ((await bar.count()) === 0) return
  // Already restored from sessionStorage — do not touch AppSelect (placeholder clears it).
  const already = bar.getByText(/UAT-S05-S07|موسم اختبار S05-S07/)
  if ((await already.count()) > 0) return
  const trigger = bar.locator('button[aria-haspopup="listbox"]').first()
  if ((await trigger.count()) === 0) return
  await trigger.click()
  await page.waitForTimeout(400)
  const opt = page
    .locator('[role="option"] button, [role="listbox"] button, [role="option"]')
    .filter({ hasText: /UAT-S05-S07|موسم اختبار S05-S07/ })
    .first()
  if ((await opt.count()) > 0) {
    await opt.click({ force: true })
    await page.waitForTimeout(600)
  }
}

async function runS05(page) {
  const title = `UAT-PR-${Date.now()}`
  await page.goto(`${BASE}/app/purchase-requests/create`, { waitUntil: 'networkidle' })
  await page.waitForSelector('form select', { timeout: 20000 })
  await page.waitForFunction(
    (unitId) =>
      [...document.querySelectorAll('form select')].some((s) =>
        [...s.options].some((o) => o.value === String(unitId)),
      ),
    fixtures.unit_id,
    { timeout: 20000 },
  )
  await page.locator('form input[type="text"]').nth(0).fill(title)
  await page.locator('form textarea').first().fill('غرض اختبار قبول S05 عبر المتصفح')
  await page.locator('form select').nth(2).selectOption(String(fixtures.unit_id))

  const needDate = new Date()
  needDate.setDate(needDate.getDate() + 14)
  const need = needDate.toISOString().slice(0, 10)
  // number[0] is optional_supplier_id (leave empty); line quantity is later.
  await page.locator('form input[type="number"]').nth(0).fill('')
  await page.locator('form input[type="text"]').nth(1).fill('صنف اختبار UAT')
  await page.locator('form input[type="number"]').nth(1).fill('3')
  await page.locator('form input[type="date"]').first().fill(need)
  const textInputs = page.locator('form input[type="text"]')
  const textCount = await textInputs.count()
  await textInputs.nth(textCount - 1).fill('تقدير غير متاح — اختبار UAT')

  await Promise.all([
    page.waitForResponse(
      (r) => r.url().includes('/api/v1/purchase-requests') && r.request().method() === 'POST',
      { timeout: 20000 },
    ).catch(() => null),
    page.locator('form button[type="submit"]').click(),
  ])
  await page.waitForTimeout(1500)
  const shot1 = await shot(page, 's05_pr_after_create')
  const url = page.url()
  const m = url.match(/\/purchase-requests\/(\d+)/)
  if (!m) {
    report.modules.s05.verdict = 'FAIL'
    report.modules.s05.notes.push(`Create did not navigate to detail: ${url}`)
    record('S05-create-pr', 'FAIL', url, shot1)
    return
  }
  const prId = Number(m[1])
  const show = await apiFromPage(page, 'GET', `/api/v1/purchase-requests/${prId}`)
  if (show.status !== 200 || show.json?.data?.id !== prId) {
    report.modules.s05.verdict = 'FAIL'
    report.modules.s05.notes.push(`API show failed status=${show.status}`)
    record('S05-create-pr', 'FAIL', `api=${show.status}`, shot1)
    return
  }
  record('S05-create-pr', 'PASS', `id=${prId} status=${show.json.data.status}`, shot1)

  // Similarity ack required before submit when warnings present
  const ack = page.getByRole('checkbox', { name: /أقر|تشابه|acknowledge|similarity/i })
  if ((await ack.count()) > 0 && !(await ack.first().isChecked())) {
    await ack.first().check({ force: true })
    await page.waitForTimeout(300)
  } else {
    const ackLabel = page.getByText(/أقر بوجود تشابه|acknowledge/i)
    if ((await ackLabel.count()) > 0) {
      await ackLabel.first().click()
      await page.waitForTimeout(300)
    }
  }
  const submitBtn = page.getByRole('button', { name: /إرسال الطلب|إرسال|Submit/i })
  if ((await submitBtn.count()) > 0) {
    const submitPost = page.waitForResponse(
      (r) =>
        r.url().includes(`/purchase-requests/${prId}`) &&
        (r.url().includes('submit') || r.request().method() === 'POST'),
      { timeout: 20000 },
    )
    await submitBtn.first().click()
    const submitRes = await submitPost.catch(() => null)
    await page.waitForTimeout(1200)
    const after = await apiFromPage(page, 'GET', `/api/v1/purchase-requests/${prId}`)
    const st = after.json?.data?.status
    if (st && st !== 'draft') {
      record(
        'S05-submit-pr',
        'PASS',
        `status=${st} http=${submitRes ? submitRes.status() : 'n/a'}`,
        await shot(page, 's05_pr_submitted'),
      )
    } else {
      record(
        'S05-submit-pr',
        'PARTIAL',
        `persisted status=${st || 'unknown'} http=${submitRes ? submitRes.status() : 'n/a'}`,
        await shot(page, 's05_pr_submit_attempt'),
      )
      report.modules.s05.notes.push('Submit transition not confirmed to non-draft')
    }
  } else {
    record('S05-submit-pr', 'PARTIAL', 'No submit control visible on detail')
    report.modules.s05.notes.push('Submit control not found')
  }

  // Cross-tenant isolation: foreign id must 404
  const iso = await apiFromPage(page, 'GET', `/api/v1/purchase-requests/${prId + 999999}`)
  if (iso.status === 404) {
    record('S05-isolation-unknown-id', 'PASS', '404 for unknown PR id')
  } else {
    record('S05-isolation-unknown-id', 'FAIL', `expected 404 got ${iso.status}`)
  }

  const fails = report.steps.filter((s) => s.step.startsWith('S05') && s.status === 'FAIL')
  report.modules.s05.verdict = fails.length ? 'FAIL' : report.modules.s05.notes.length ? 'PARTIAL' : 'PASS'
}

async function runS06(page) {
  await selectSeason(page, fixtures.season_id)
  await page.goto(`${BASE}/app/season-projects`, { waitUntil: 'networkidle' })
  await page.waitForTimeout(1000)
  if (page.url().includes('/login')) throw new Error('S06 lost session before projects page')
  const createBtn = page.locator('button').filter({ hasText: /إنشاء|جديد/ }).first()
  await createBtn.click({ timeout: 15000 })
  await page.waitForTimeout(500)
  const title = `UAT-SPJ-${Date.now()}`
  const drawer = page.locator('[role="dialog"]').last()
  await drawer.waitFor({ state: 'visible', timeout: 10000 })
  await drawer.locator('input').first().fill(title)
  const desc = drawer.locator('textarea').first()
  if ((await desc.count()) > 0) await desc.fill('وصف مشروع اختبار S06')
  // Owner employee required to enable Activate — pick via AppSelect
  const ownerTrigger = drawer.locator('button[aria-haspopup="listbox"]').first()
  if ((await ownerTrigger.count()) > 0) {
    await ownerTrigger.click()
    await page.waitForTimeout(400)
    const ownerOpt = page
      .locator('[role="listbox"] button, [role="option"] button')
      .filter({ hasText: /.+/, hasNotText: /اختر|بدون|Select|None/i })
      .first()
    if ((await ownerOpt.count()) > 0) await ownerOpt.click({ force: true })
    await page.waitForTimeout(300)
  }

  await Promise.all([
    page.waitForResponse(
      (r) => r.url().includes('season-projects') && r.request().method() === 'POST',
      { timeout: 20000 },
    ).catch(() => null),
    drawer.locator('button[type="submit"]').click(),
  ])
  await page.waitForTimeout(1500)
  const shot1 = await shot(page, 's06_project_after_create')
  const url = page.url()
  const m = url.match(/\/season-projects\/(\d+)/)
  if (!m) {
    // maybe stayed on list — query API
    const list = await apiFromPage(
      page,
      'GET',
      `/api/v1/seasons/${fixtures.season_id}/season-projects?q=${encodeURIComponent(title)}`,
    )
    const row = list.json?.data?.find?.((r) => r.title === title) || list.json?.data?.[0]
    if (!row) {
      report.modules.s06.verdict = 'FAIL'
      record('S06-create-project', 'FAIL', `no detail nav / list hit; url=${url}`, shot1)
      return
    }
    record('S06-create-project', 'PASS', `id=${row.id} via list API status=${row.status}`, shot1)
    await page.goto(`${BASE}/app/season-projects/${row.id}`, { waitUntil: 'domcontentloaded' })
  } else {
    const id = Number(m[1])
    const show = await apiFromPage(page, 'GET', `/api/v1/seasons/${fixtures.season_id}/season-projects/${id}`)
    if (show.status !== 200) {
      // try alternate path
      const alt = await apiFromPage(page, 'GET', `/api/v1/season-projects/${id}`)
      if (alt.status !== 200) {
        report.modules.s06.verdict = 'FAIL'
        record('S06-create-project', 'FAIL', `API show ${show.status}/${alt.status}`, shot1)
        return
      }
      record('S06-create-project', 'PASS', `id=${id} status=${alt.json?.data?.status}`, shot1)
    } else {
      record('S06-create-project', 'PASS', `id=${id} status=${show.json?.data?.status}`, shot1)
    }
  }

  // Ensure owner_employee_id so Activate enables (product gate)
  const detailUrl = page.url()
  const idMatch = detailUrl.match(/\/season-projects\/(\d+)/)
  const projectId = idMatch ? Number(idMatch[1]) : null
  if (projectId && fixtures.employee_id) {
    const cur = await apiFromPage(page, 'GET', `/api/v1/season-projects/${projectId}`)
    if (cur.status === 200 && !cur.json?.data?.owner_employee_id) {
      await apiFromPage(page, 'PATCH', `/api/v1/season-projects/${projectId}`, {
        title: cur.json.data.title,
        description: cur.json.data.description,
        owner_employee_id: fixtures.employee_id,
      })
      await page.reload({ waitUntil: 'networkidle' })
      await page.waitForTimeout(800)
    }
  }

  const activate = page.getByRole('button', { name: /تفعيل|Activate/i })
  if ((await activate.count()) > 0) {
    const disabled = await activate.first().isDisabled()
    if (disabled) {
      record(
        'S06-activate-project',
        'PARTIAL',
        'activate visible but disabled (owner/employee or season gate)',
        await shot(page, 's06_activate_disabled'),
      )
      report.modules.s06.notes.push('Activate disabled after owner attempt')
    } else {
      const actPost = page.waitForResponse(
        (r) =>
          r.url().includes('season-projects') &&
          r.url().includes('activate') &&
          r.request().method() === 'POST',
        { timeout: 20000 },
      )
      await activate.first().click()
      // Confirm dialog if present
      const confirm = page.getByRole('button', { name: /تأكيد|Confirm|نعم|تفعيل/i })
      if ((await confirm.count()) > 1) {
        await confirm.last().click().catch(() => {})
      } else if ((await confirm.count()) === 1) {
        await confirm.first().click().catch(() => {})
      }
      const actRes = await actPost.catch(() => null)
      await page.waitForTimeout(1000)
      const after = projectId
        ? await apiFromPage(page, 'GET', `/api/v1/season-projects/${projectId}`)
        : { status: 0, json: null }
      const st = after.json?.data?.status
      if (st === 'active' || (actRes && actRes.status() < 400)) {
        record(
          'S06-activate-project',
          'PASS',
          `status=${st || 'unknown'} activateHttp=${actRes ? actRes.status() : 'n/a'}`,
          await shot(page, 's06_activated'),
        )
      } else {
        record(
          'S06-activate-project',
          'PARTIAL',
          `clicked; status=${st || 'unknown'} http=${actRes ? actRes.status() : 'n/a'}`,
          await shot(page, 's06_activate_attempt'),
        )
        report.modules.s06.notes.push('Activate clicked but status not active')
      }
    }
  } else {
    record('S06-activate-project', 'PARTIAL', 'activate control not visible')
    report.modules.s06.notes.push('Activate control not found')
  }

  const fails = report.steps.filter((s) => s.step.startsWith('S06') && s.status === 'FAIL')
  report.modules.s06.verdict = fails.length ? 'FAIL' : report.modules.s06.notes.length ? 'PARTIAL' : 'PASS'
}

async function runS07(page) {
  await selectSeason(page, fixtures.season_id)

  // Risk
  await page.goto(`${BASE}/app/risks`, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(800)
  if (page.url().includes('/login')) throw new Error('S07 lost session before risks page')
  await page.getByTestId('risk-create-btn').click({ timeout: 15000 })
  await page.waitForTimeout(400)
  const riskTitle = `UAT-RSK-${Date.now()}`
  const riskDrawer = page.getByTestId('risk-form-drawer')
  await riskDrawer.waitFor({ state: 'visible', timeout: 10000 })
  const riskTitleInput = page.getByTestId('risk-title-input')
  await riskTitleInput.click()
  await riskTitleInput.evaluate((el) => {
    el.value = ''
    el.dispatchEvent(new Event('input', { bubbles: true }))
  })
  await page.keyboard.type(riskTitle, { delay: 20 })
  const riskDesc = riskDrawer.locator('textarea').first()
  if ((await riskDesc.count()) > 0) {
    await riskDesc.click()
    await page.keyboard.type('وصف خطر اختبار', { delay: 10 })
  }
  const riskPost = page.waitForResponse(
    (r) =>
      r.url().includes('/api/v1/risks') &&
      r.request().method() === 'POST' &&
      !r.url().includes('assess'),
    { timeout: 20000 },
  )
  await page.getByTestId('risk-form-submit').click()
  const riskRes = await riskPost.catch(() => null)
  let riskId = null
  if (riskRes) {
    report.modules.s07.notes.push(`risk POST status=${riskRes.status()}`)
    const body = await riskRes.json().catch(() => null)
    riskId = body?.data?.id ?? null
  }
  await page.waitForURL(/\/app\/risks\/\d+/, { timeout: 15000 }).catch(() => {})
  await page.waitForTimeout(500)
  const riskShot = await shot(page, 's07_risk_created')
  const riskUrl = page.url()
  const rm = riskUrl.match(/\/risks\/(\d+)/)
  if (rm) riskId = Number(rm[1])
  if (!riskId) {
    report.modules.s07.verdict = 'FAIL'
    record('S07-create-risk', 'FAIL', `no id; url=${riskUrl}`, riskShot)
  } else {
    const show = await apiFromPage(page, 'GET', `/api/v1/risks/${riskId}`)
    if (show.status === 200 && show.json?.data?.title) {
      record(
        'S07-create-risk',
        'PASS',
        `id=${riskId} title=${show.json.data.title} persisted`,
        riskShot,
      )
      // Assess form on detail (likelihood/impact buttons + submit)
      const assessSubmit = page.getByTestId('risk-assess-submit')
      if ((await assessSubmit.count()) > 0) {
        await page.getByTestId('risk-likelihood-3').click().catch(() => {})
        await page.getByTestId('risk-impact-3').click().catch(() => {})
        const assessPost = page.waitForResponse(
          (r) => r.url().includes('risks') && r.url().includes('assess') && r.request().method() === 'POST',
          { timeout: 15000 },
        )
        await assessSubmit.click()
        const aRes = await assessPost.catch(() => null)
        await page.waitForTimeout(800)
        const after = await apiFromPage(page, 'GET', `/api/v1/risks/${riskId}`)
        const st = after.json?.data?.status
        if ((st && st !== 'defined') || (aRes && aRes.status() < 400)) {
          record('S07-assess-risk', 'PASS', `status=${st} http=${aRes ? aRes.status() : 'n/a'}`)
        } else {
          record('S07-assess-risk', 'PARTIAL', `status=${st || 'unknown'} http=${aRes ? aRes.status() : 'n/a'}`)
        }
      } else {
        record('S07-assess-risk', 'PARTIAL', 'assess control not found on detail')
      }
    } else {
      report.modules.s07.verdict = 'FAIL'
      record('S07-create-risk', 'FAIL', `API ${show.status}`, riskShot)
    }
  }

  // Complaint
  await page.goto(`${BASE}/app/complaints`, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(800)
  await page.getByTestId('complaint-create-btn').click({ timeout: 15000 })
  await page.waitForTimeout(400)
  const cTitle = `UAT-CMP-${Date.now()}`
  const cDrawer = page.getByTestId('complaint-form-drawer')
  await cDrawer.waitFor({ state: 'visible', timeout: 10000 })
  const cTitleInput = page.getByTestId('complaint-title-input')
  await cTitleInput.click()
  await cTitleInput.evaluate((el) => {
    el.value = ''
    el.dispatchEvent(new Event('input', { bubbles: true }))
  })
  await page.keyboard.type(cTitle, { delay: 20 })
  const cBody = page.getByTestId('complaint-body-input')
  await cBody.click()
  await page.keyboard.type('نص شكوى اختبار للمتصفح', { delay: 10 })
  // AppSelect category — pick first real category (skip placeholders)
  const catTrigger = cDrawer.locator('button[aria-haspopup="listbox"]').first()
  if ((await catTrigger.count()) > 0) {
    await catTrigger.click()
    await page.waitForTimeout(400)
    const opt = page
      .locator('[role="listbox"] button, [role="option"] button, [role="option"]')
      .filter({ hasText: /.+/, hasNotText: /اختر|بدون|Select|None/i })
      .first()
    if ((await opt.count()) > 0) await opt.click({ force: true })
    await page.waitForTimeout(300)
  }
  const cPost = page.waitForResponse(
    (r) => r.url().includes('/api/v1/complaints') && r.request().method() === 'POST',
    { timeout: 20000 },
  )
  await page.getByTestId('complaint-form-submit').click()
  const cRes = await cPost.catch(() => null)
  let cId = null
  if (cRes) {
    report.modules.s07.notes.push(`complaint POST status=${cRes.status()}`)
    const body = await cRes.json().catch(() => null)
    cId = body?.data?.id ?? null
    if (cRes.status() >= 400) {
      report.modules.s07.notes.push(`complaint POST body=${JSON.stringify(body).slice(0, 300)}`)
    }
  }
  await page.waitForURL(/\/app\/complaints\/\d+/, { timeout: 15000 }).catch(() => {})
  await page.waitForTimeout(500)
  const cShot = await shot(page, 's07_complaint_created')
  const cUrl = page.url()
  const cm = cUrl.match(/\/complaints\/(\d+)/)
  if (cm) cId = Number(cm[1])
  if (!cId) {
    report.modules.s07.notes.push('Complaint create missing id / navigation')
    record('S07-create-complaint', 'FAIL', cUrl, cShot)
  } else {
    const alt = await apiFromPage(page, 'GET', `/api/v1/complaints/${cId}`)
    if (alt.status === 200) {
      record(
        'S07-create-complaint',
        'PASS',
        `id=${cId} status=${alt.json?.data?.status} persisted`,
        cShot,
      )
      // Wait for detail lifecycle panel (create screenshot may be mid-load)
      await page
        .getByTestId('complaint-lifecycle-actions')
        .or(page.getByTestId('complaint-start-review-btn'))
        .waitFor({ state: 'visible', timeout: 20000 })
        .catch(() => {})
      await page.waitForTimeout(500)
      // Workflow: start review → assign employee → resolve
      const startReview = page.getByTestId('complaint-start-review-btn')
      if ((await startReview.count()) > 0) {
        const srPost = page.waitForResponse(
          (r) =>
            r.url().includes('complaints') &&
            (r.url().includes('start-review') || r.url().includes('review')) &&
            r.request().method() === 'POST',
          { timeout: 15000 },
        )
        await startReview.click()
        await srPost.catch(() => null)
        await page.waitForTimeout(1200)
        await page
          .getByTestId('complaint-assign-btn')
          .waitFor({ state: 'visible', timeout: 10000 })
          .catch(() => {})
      }
      const lifecycle = page.getByTestId('complaint-lifecycle-actions')
      const assigneeTrigger = lifecycle.locator('button[aria-haspopup="listbox"]').first()
      if ((await assigneeTrigger.count()) > 0) {
        await assigneeTrigger.click()
        await page.waitForTimeout(400)
        const empOpt = page
          .locator('[role="listbox"] button, [role="option"] button')
          .filter({ hasText: /.+/, hasNotText: /اختر|بدون|Select|None/i })
          .first()
        if ((await empOpt.count()) > 0) await empOpt.click({ force: true })
        await page.waitForTimeout(300)
      }
      const assignBtn = page.getByTestId('complaint-assign-btn')
      if ((await assignBtn.count()) > 0) {
        const assignPost = page.waitForResponse(
          (r) => r.url().includes('complaints') && r.url().includes('assign') && r.request().method() === 'POST',
          { timeout: 15000 },
        )
        await assignBtn.click()
        const aRes = await assignPost.catch(() => null)
        await page.waitForTimeout(800)
        const afterAssign = await apiFromPage(page, 'GET', `/api/v1/complaints/${cId}`)
        if (afterAssign.json?.data?.assignee_employee_id || (aRes && aRes.status() < 400)) {
          record(
            'S07-assign-complaint',
            'PASS',
            `assignee=${afterAssign.json?.data?.assignee_employee_id ?? 'set'} http=${aRes ? aRes.status() : 'n/a'}`,
          )
        } else {
          record('S07-assign-complaint', 'PARTIAL', `http=${aRes ? aRes.status() : 'n/a'} status=${afterAssign.json?.data?.status}`)
        }
      } else {
        record('S07-assign-complaint', 'PARTIAL', 'assign control not visible on detail')
      }
      const resolveInput = page.getByTestId('complaint-resolution-input')
      const resolveBtn = page.getByTestId('complaint-resolve-btn')
      if ((await resolveBtn.count()) > 0) {
        if ((await resolveInput.count()) > 0) {
          await resolveInput.click()
          await page.keyboard.type('تم حل الشكوى في اختبار UAT', { delay: 10 })
        }
        const resolvePost = page.waitForResponse(
          (r) => r.url().includes('complaints') && r.url().includes('resolve') && r.request().method() === 'POST',
          { timeout: 15000 },
        )
        await resolveBtn.click()
        const rRes = await resolvePost.catch(() => null)
        await page.waitForTimeout(800)
        const afterResolve = await apiFromPage(page, 'GET', `/api/v1/complaints/${cId}`)
        const rst = afterResolve.json?.data?.status
        if (rst === 'resolved' || (rRes && rRes.status() < 400)) {
          record('S07-resolve-complaint', 'PASS', `status=${rst} http=${rRes ? rRes.status() : 'n/a'}`)
        } else {
          record('S07-resolve-complaint', 'PARTIAL', `status=${rst || 'unknown'} http=${rRes ? rRes.status() : 'n/a'}`)
        }
      } else {
        record('S07-resolve-complaint', 'PARTIAL', 'resolve control not visible / gated')
      }
    } else {
      record('S07-create-complaint', 'FAIL', `API ${alt.status}`, cShot)
    }
  }

  const fails = report.steps.filter((s) => s.step.startsWith('S07') && s.status === 'FAIL')
  const partials = report.steps.filter((s) => s.step.startsWith('S07') && s.status === 'PARTIAL')
  if (fails.length) report.modules.s07.verdict = 'FAIL'
  else if (partials.length) report.modules.s07.verdict = 'PARTIAL'
  else report.modules.s07.verdict = 'PASS'
}

async function runViewer(page) {
  await login(page, VIEWER)
  await selectSeason(page, fixtures.season_id)
  await page.goto(`${BASE}/app/purchase-requests/create`, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1000)
  const denied = page.url().includes('/login') || (await page.locator('text=/غير مصرح|ممنوع|403|Forbidden/i').count()) > 0
  const createBlocked = !(await page.locator('form button[type="submit"]').count())
  record(
    'S05-viewer-create-denied',
    denied || createBlocked ? 'PASS' : 'FAIL',
    `url=${page.url()} denied=${denied} noSubmit=${createBlocked}`,
    await shot(page, 'viewer_pr_create'),
  )

  await page.goto(`${BASE}/app/risks`, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(800)
  const riskCreate = await page.getByTestId('risk-create-btn').count()
  record(
    'S07-viewer-create-hidden',
    riskCreate === 0 ? 'PASS' : 'FAIL',
    `risk-create-btn count=${riskCreate}`,
    await shot(page, 'viewer_risks'),
  )
}

function finish() {
  report.finished_at = new Date().toISOString()
  const moduleVerdicts = Object.values(report.modules).map((m) => m.verdict)
  if (report.steps.some((s) => s.status === 'FAIL') || moduleVerdicts.includes('FAIL')) {
    report.verdict = 'FAIL'
  } else if (moduleVerdicts.includes('PARTIAL') || report.steps.some((s) => s.status === 'PARTIAL')) {
    report.verdict = 'PARTIAL'
  } else if (moduleVerdicts.every((v) => v === 'PASS')) {
    report.verdict = 'PASS'
  } else {
    report.verdict = 'NOT_RUN'
  }
  const out = path.join(EVIDENCE, `${stamp}_report.json`)
  fs.writeFileSync(out, JSON.stringify(report, null, 2), 'utf8')
  fs.writeFileSync(path.join(__dirname, 'last-report.json'), JSON.stringify(report, null, 2), 'utf8')
  console.log(`\nModules: S05=${report.modules.s05.verdict} S06=${report.modules.s06.verdict} S07=${report.modules.s07.verdict}`)
  console.log(`Verdict: ${report.verdict}`)
  console.log(`Report: ${out}`)
  if (report.verdict === 'FAIL') process.exit(1)
}

async function main() {
  const env = await verifyEnvironment()
  if (!env.ok) {
    report.verdict = 'NOT_RUN'
    report.not_run_reason = env.reason
    record('environment-gate', 'NOT_RUN', env.reason)
    finish()
    return
  }
  report.isolation.database = env.database
  report.isolation.verified_disposable = true
  record('environment-gate', 'PASS', env.database)

  let browser
  try {
    browser = await chromium.launch({
      channel: process.env.UAT_BROWSER_CHANNEL || 'chrome',
      headless: true,
    })
    const ownerCtx = await browser.newContext({ locale: 'ar-SA', viewport: { width: 1440, height: 900 } })
    const page = await ownerCtx.newPage()
    await login(page, OWNER)
    record('auth-owner', 'PASS', OWNER.email, await shot(page, '00_owner'))

    try {
      await runS05(page)
    } catch (err) {
      report.modules.s05.verdict = 'FAIL'
      record('S05-fatal', 'FAIL', String(err), await shot(page, 's05_fatal').catch(() => null))
    }
    try {
      if (page.url().includes('/login')) await login(page, OWNER)
      await runS06(page)
    } catch (err) {
      report.modules.s06.verdict = 'FAIL'
      record('S06-fatal', 'FAIL', String(err), await shot(page, 's06_fatal').catch(() => null))
    }
    try {
      if (page.url().includes('/login')) await login(page, OWNER)
      await runS07(page)
    } catch (err) {
      report.modules.s07.verdict = 'FAIL'
      record('S07-fatal', 'FAIL', String(err), await shot(page, 's07_fatal').catch(() => null))
    }
    await ownerCtx.close()

    const viewerCtx = await browser.newContext({ locale: 'ar-SA', viewport: { width: 1440, height: 900 } })
    const viewerPage = await viewerCtx.newPage()
    await runViewer(viewerPage)
    await viewerCtx.close()
  } catch (err) {
    report.verdict = 'FAIL'
    record('fatal', 'FAIL', String(err))
  } finally {
    await browser?.close().catch(() => {})
    finish()
  }
}

main()
