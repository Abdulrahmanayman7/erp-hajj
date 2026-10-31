import { chromium } from 'playwright-core'

const browser = await chromium.launch({ headless: true, channel: 'chrome' })
const page = await browser.newPage()
const api = []
page.on('response', async (r) => {
  if (r.url().includes('/api/') || r.url().includes('sanctum')) {
    let body = ''
    try {
      body = (await r.text()).slice(0, 300)
    } catch {}
    api.push(`${r.status()} ${r.request().method()} ${r.url()} :: ${body}`)
  }
})
page.on('console', (m) => console.log('CONSOLE', m.type(), m.text()))

await page.goto('http://localhost:5173/login', { waitUntil: 'domcontentloaded' })
await page.waitForSelector('#login-email', { timeout: 15000 })
await page.fill('#login-email', 'uat.owner@example.com')
await page.fill('#login-password', 'Admin@12345')
await page.click('button[type="submit"]')
await page.waitForTimeout(5000)
console.log('url', page.url())
console.log('body', (await page.locator('body').innerText()).slice(0, 800))
console.log('api\n', api.join('\n'))
await page.screenshot({ path: 'evidence/debug_auth.png', fullPage: true })
await browser.close()
