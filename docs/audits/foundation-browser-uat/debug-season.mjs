import { chromium } from 'playwright-core'
import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const L = JSON.parse(fs.readFileSync(path.join(__dirname, 'labels.json'), 'utf8'))
const BASE = 'http://localhost:5173'

const browser = await chromium.launch({ headless: true, channel: 'chrome' })
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } })
const api = []
page.on('response', async (r) => {
  if (r.url().includes('/api/v1/seasons')) {
    let t = ''
    try { t = (await r.text()).slice(0, 500) } catch {}
    api.push(`${r.status()} ${r.request().method()} ${r.url()} ${t}`)
  }
})

await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' })
await page.fill('#login-email', 'uat.owner@example.com')
await page.fill('#login-password', 'Admin@12345')
await Promise.all([
  page.waitForURL(/\/app/),
  page.locator('button[type="submit"]').click(),
])

await page.goto(`${BASE}/app/seasons`, { waitUntil: 'domcontentloaded' })
await page.waitForTimeout(1500)
console.log('body snippet', (await page.locator('body').innerText()).slice(0, 600))
console.log('create btn count', await page.getByRole('button', { name: L.createSeason }).count())
console.log('all buttons', await page.locator('button').evaluateAll((els) => els.map((e) => e.innerText.trim()).filter(Boolean).slice(0, 30)))

await page.getByRole('button', { name: L.createSeason }).click()
await page.waitForTimeout(800)
console.log('dialog?', await page.getByRole('dialog').count())
await page.screenshot({ path: 'evidence/debug_season_drawer.png', fullPage: true })

const code = 'DBG' + Date.now().toString().slice(-4)
await page.getByText(L.seasonCode, { exact: true }).first().locator('xpath=ancestor::label[1]//input').fill(code)
await page.getByText(L.seasonName, { exact: true }).first().locator('xpath=ancestor::label[1]//input').fill('Debug Season')
await page.getByText(L.hajjYear, { exact: true }).first().locator('xpath=ancestor::label[1]//input').fill('1448')
await page.getByText(L.startsOn, { exact: true }).first().locator('xpath=ancestor::label[1]//input').fill('2027-05-01')
await page.getByText(L.endsOn, { exact: true }).first().locator('xpath=ancestor::label[1]//input').fill('2027-06-30')
await page.getByRole('button', { name: L.createSeasonCta }).click()
await page.waitForTimeout(2000)
console.log('after create body', (await page.locator('body').innerText()).slice(0, 800))
console.log('api', api)
await page.screenshot({ path: 'evidence/debug_season_after.png', fullPage: true })
await browser.close()
