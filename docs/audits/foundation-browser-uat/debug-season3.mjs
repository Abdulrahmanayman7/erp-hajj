import { chromium } from 'playwright-core'
import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const L = JSON.parse(fs.readFileSync(path.join(__dirname, 'labels.json'), 'utf8'))
const BASE = 'http://localhost:5173'
const code = `UAT${Date.now().toString().slice(-6)}`
const year = String(1510 + (Date.now() % 80))

const browser = await chromium.launch({ headless: true, channel: 'chrome' })
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } })
page.on('request', (r) => {
  if (r.url().includes('/api/') || r.url().includes('sanctum')) {
    console.log('REQ', r.method(), r.url())
  }
})
page.on('requestfailed', (r) => {
  console.log('FAIL', r.method(), r.url(), r.failure()?.errorText)
})
page.on('response', (r) => {
  if (r.url().includes('/api/') || r.url().includes('sanctum')) {
    console.log('RES', r.status(), r.request().method(), r.url())
  }
})

await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' })
await page.fill('#login-email', 'uat.owner@example.com')
await page.fill('#login-password', 'Admin@12345')
await Promise.all([page.waitForURL(/\/app/), page.locator('button[type="submit"]').click()])
await page.waitForFunction(() => (document.body?.innerText || '').includes('UAT Owner'))

await page.goto(`${BASE}/app/seasons`, { waitUntil: 'domcontentloaded' })
await page.waitForFunction(() => (document.body?.innerText || '').includes('إنشاء موسم'))
await page.getByRole('button', { name: L.createSeason }).click()
await page.getByRole('dialog').waitFor()

for (const [label, value] of [
  [L.seasonCode, code],
  [L.seasonName, `Season ${code}`],
  [L.hajjYear, year],
  [L.startsOn, '2027-05-01'],
  [L.endsOn, '2027-06-30'],
]) {
  const input = page.getByText(label, { exact: true }).first().locator('xpath=ancestor::label[1]//input')
  await input.click()
  await input.fill('')
  await input.type(String(value), { delay: 20 })
  await input.dispatchEvent('input')
  await input.dispatchEvent('change')
}

const formState = await page.evaluate(() => {
  // peek vue form if exposed - fallback dialog inputs
  const inputs = [...document.querySelectorAll('[role="dialog"] input')].map((el) => ({
    type: el.getAttribute('type'),
    value: el.value,
    disabled: el.disabled,
  }))
  return inputs
})
console.log('inputs', formState)

await page.getByRole('button', { name: L.createSeasonCta }).click()
await page.waitForTimeout(8000)
console.log('dialog', await page.getByRole('dialog').innerText().catch(() => 'gone'))
console.log('has code', (await page.locator('body').innerText()).includes(code))
await browser.close()
