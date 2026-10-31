import { chromium } from 'playwright-core'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const browser = await chromium.launch({ channel: 'chrome', headless: true })
const page = await browser.newPage()
page.on('console', (m) => console.log('CONSOLE', m.type(), m.text()))
page.on('pageerror', (e) => console.log('PAGEERROR', e.message))
await page.goto('http://localhost:5173/login', { waitUntil: 'networkidle', timeout: 60000 })
console.log('URL', page.url())
console.log('HTML', (await page.content()).slice(0, 1500))
await page.screenshot({ path: path.join(__dirname, 'evidence', 'debug_login.png'), fullPage: true })
await browser.close()
