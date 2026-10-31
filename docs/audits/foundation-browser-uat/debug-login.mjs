import { chromium } from 'playwright-core'

const browser = await chromium.launch({ headless: true, channel: 'chrome' })
const context = await browser.newContext()
const page = await context.newPage()
page.on('console', (m) => console.log('CONSOLE', m.type(), m.text()))
page.on('pageerror', (e) => console.log('PAGEERROR', e.message))

await page.addInitScript(() => {
  try {
    navigator.serviceWorker.getRegistrations().then((regs) => regs.forEach((r) => r.unregister()))
  } catch {}
})

await page.goto('http://localhost:5173/login', { waitUntil: 'domcontentloaded', timeout: 60000 })
await page.waitForTimeout(5000)

const info = await page.evaluate(() => {
  const appEl = document.querySelector('#app')
  const vue = appEl && appEl.__vue_app__
  const route = vue?.config?.globalProperties?.$route
  return {
    href: location.href,
    name: route?.name,
    path: route?.path,
    meta: route?.meta ? JSON.parse(JSON.stringify(route.meta)) : null,
    matched: (route?.matched || []).map((m) => ({
      path: m.path,
      name: m.name,
      meta: JSON.parse(JSON.stringify(m.meta || {})),
    })),
    hasEmail: !!document.querySelector('#login-email'),
    hasAppShell: !!document.querySelector('.app-shell'),
    bodyStart: document.body?.innerText?.slice(0, 200),
  }
})
console.log(JSON.stringify(info, null, 2))
await page.screenshot({ path: 'evidence/debug_login_meta.png', fullPage: true })
await browser.close()
