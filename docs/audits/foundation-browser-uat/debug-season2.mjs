import { chromium } from 'playwright-core'
import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const L = JSON.parse(fs.readFileSync(path.join(__dirname, 'labels.json'), 'utf8'))
const BASE = 'http://localhost:5173'
const code = `UAT${Date.now().toString().slice(-6)}`
const year = String(1500 + (Date.now() % 90))

async function fillByLabel(page, labelText, value) {
  const label = page.getByText(labelText, { exact: true }).first()
  const container = label.locator('xpath=ancestor::label[1]')
  const input = container.locator('input, textarea').first()
  console.log('fill', labelText, 'count', await input.count())
  await input.fill(String(value))
  console.log('value now', await input.inputValue())
}

const browser = await chromium.launch({ headless: true, channel: 'chrome' })
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } })
const api = []
page.on('response', async (r) => {
  if (r.url().includes('/api/v1/seasons') && r.request().method() === 'POST') {
    api.push(`${r.status()} ${(await r.text()).slice(0, 400)}`)
  }
})

await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' })
await page.fill('#login-email', 'uat.owner@example.com')
await page.fill('#login-password', 'Admin@12345')
await Promise.all([page.waitForURL(/\/app/), page.locator('button[type="submit"]').click()])
await page.waitForFunction(() => (document.body?.innerText || '').includes('UAT Owner'))

await page.goto(`${BASE}/app/seasons`, { waitUntil: 'domcontentloaded' })
await page.waitForFunction(() => (document.body?.innerText || '').includes('UAT Owner'))
await page.getByRole('button', { name: L.createSeason }).click()
await page.getByRole('dialog').waitFor()
await fillByLabel(page, L.seasonCode, code)
await fillByLabel(page, L.seasonName, `Season ${code}`)
await fillByLabel(page, L.hajjYear, year)
await fillByLabel(page, L.startsOn, '2027-05-01')
await fillByLabel(page, L.endsOn, '2027-06-30')
await page.screenshot({ path: 'evidence/debug_season_filled.png', fullPage: true })
await page.getByRole('button', { name: L.createSeasonCta }).click()
await page.waitForTimeout(2000)
console.log('dialog still?', await page.getByRole('dialog').count())
console.log('dialog text', await page.getByRole('dialog').innerText().catch(() => 'none'))
console.log('list has code?', (await page.locator('body').innerText()).includes(code))
console.log('api', api)
await page.screenshot({ path: 'evidence/debug_season_result.png', fullPage: true })
await browser.close()
