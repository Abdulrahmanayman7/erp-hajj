import { test, expect } from '@playwright/test'
import fs from 'node:fs'
import path from 'node:path'

import {
  STATIC_APP_ROUTES,
  MOBILE_VIEWPORTS,
  TABLET_VIEWPORTS,
  DESKTOP_VIEWPORT,
  setViewport,
  measureDocumentOverflow,
  captureMobileEvidence,
  type OverflowReport,
} from '../src/helpers/mobile'
import { expectNoFatalCrash, gotoApp } from '../src/helpers/ui'

const overflowReports: OverflowReport[] = []
const blocked: Array<{ route: string; reason: string; viewport: string }> = []
const evidenceIndex: string[] = []

test.describe.configure({ mode: 'serial' })

async function scanRoutesForOverflow(
  page: import('@playwright/test').Page,
  vp: { name: string; width: number; height: number },
  routes: string[],
) {
  await setViewport(page, vp)
  for (const route of routes) {
    await gotoApp(page, route)
    await expectNoFatalCrash(page)
    const url = page.url()
    if (url.includes('/login')) {
      blocked.push({ route, reason: 'redirected-to-login', viewport: vp.name })
      continue
    }
    if (url.includes('/403') || url.includes('/access-blocked')) {
      blocked.push({ route, reason: 'forbidden', viewport: vp.name })
      continue
    }
    const measured = await measureDocumentOverflow(page)
    if (measured.overflowPx > 2) {
      overflowReports.push({ route, viewport: vp.name, ...measured })
    }
  }
}

test.describe('Mobile responsive QA', () => {
  test.afterAll(() => {
    const outDir = path.join(process.cwd(), 'test-results', 'mobile-evidence')
    fs.mkdirSync(outDir, { recursive: true })
    fs.writeFileSync(
      path.join(outDir, 'overflow-report.json'),
      JSON.stringify(
        {
          overflowCount: overflowReports.length,
          overflowReports,
          blocked,
          evidenceIndex,
          routesScanned: STATIC_APP_ROUTES.length,
        },
        null,
        2,
      ),
      'utf8',
    )
  })

  test('bottom nav is visible and usable on mobile', async ({ page }) => {
    await setViewport(page, MOBILE_VIEWPORTS[2]!)
    await gotoApp(page, '/app')
    await expectNoFatalCrash(page)

    const nav = page.locator('nav.app-bottom-nav')
    await expect(nav).toBeVisible()
    await expect(nav).toBeInViewport()

    const box = await nav.boundingBox()
    expect(box, 'bottom nav missing box').toBeTruthy()
    expect(box!.y + box!.height).toBeLessThanOrEqual(page.viewportSize()!.height + 1)
    expect(box!.width).toBeGreaterThan(page.viewportSize()!.width * 0.9)

    const tasks = page.getByRole('link', { name: /المهام|Tasks/i }).first()
    if (await tasks.isVisible().catch(() => false)) {
      await tasks.click()
      await expect(page).toHaveURL(/\/app\/tasks/)
      await expect(nav).toBeVisible()
    }

    await page.getByRole('button', { name: /المزيد|More/i }).click()
    const more = page.locator('#app-mobile-more-sheet, [data-test="app-mobile-more-sheet"]').first()
    await expect(more).toBeVisible({ timeout: 10_000 })
    await page.keyboard.press('Escape')
  })

  test('bottom nav hidden on tablet/desktop shell', async ({ page }) => {
    await setViewport(page, TABLET_VIEWPORTS[0]!)
    await gotoApp(page, '/app')
    await expect(page.locator('nav.app-bottom-nav')).toBeHidden()

    await setViewport(page, DESKTOP_VIEWPORT)
    await gotoApp(page, '/app')
    await expect(page.locator('nav.app-bottom-nav')).toBeHidden()
  })

  test('drawer create affordance fits mobile viewport', async ({ page }) => {
    await setViewport(page, MOBILE_VIEWPORTS[2]!)
    await gotoApp(page, '/app/meetings')
    await expectNoFatalCrash(page)

    const create = page.getByRole('button', { name: /اجتماع|إضافة|جديد|Create|Meeting/i }).first()
    if (!(await create.isVisible().catch(() => false))) {
      test.skip(true, 'create CTA not visible (permission/fixture)')
    }
    await create.click()
    const drawer = page.locator('[data-test="app-form-drawer"], [role="dialog"]').first()
    await expect(drawer).toBeVisible({ timeout: 10_000 })
    const panel = page.locator('[data-test="app-form-drawer-panel"], aside.app-drawer-panel').first()
    if (await panel.isVisible().catch(() => false)) {
      const box = await panel.boundingBox()
      expect(box!.width).toBeLessThanOrEqual(page.viewportSize()!.width + 1)
      expect(box!.x).toBeGreaterThanOrEqual(-1)
    }
    const measured = await measureDocumentOverflow(page)
    expect(measured.overflowPx).toBeLessThanOrEqual(2)
    await page.keyboard.press('Escape')
  })

  test('no document overflow on static routes @ narrow-320', async ({ page }) => {
    await scanRoutesForOverflow(page, MOBILE_VIEWPORTS[0]!, STATIC_APP_ROUTES)
  })

  test('no document overflow on static routes @ iphone-375', async ({ page }) => {
    await scanRoutesForOverflow(page, MOBILE_VIEWPORTS[2]!, STATIC_APP_ROUTES)
  })

  test('no document overflow on static routes @ large-430', async ({ page }) => {
    await scanRoutesForOverflow(page, MOBILE_VIEWPORTS[4]!, STATIC_APP_ROUTES)
  })

  test('no document overflow on static routes @ tablet-portrait', async ({ page }) => {
    await scanRoutesForOverflow(page, TABLET_VIEWPORTS[0]!, STATIC_APP_ROUTES)
  })

  test('overflow scan summary has zero document overflows', async () => {
    expect(
      overflowReports,
      `Document horizontal overflow found:\n${JSON.stringify(overflowReports, null, 2)}`,
    ).toEqual([])
  })

  test('screenshot evidence for critical modules on iphone-375', async ({ page }) => {
    const vp = MOBILE_VIEWPORTS[2]!
    await setViewport(page, vp)
    const critical = [
      { module: 'dashboard', route: '/app' },
      { module: 'tasks', route: '/app/tasks' },
      { module: 'transport', route: '/app/transport-vehicles' },
      { module: 'gps', route: '/app/gps-fleet-map' },
      { module: 'procurement', route: '/app/purchase-requests' },
      { module: 'accommodation', route: '/app/accommodation' },
      { module: 'risks', route: '/app/risks' },
      { module: 'organization', route: '/app/organization' },
    ]
    for (const item of critical) {
      await gotoApp(page, item.route)
      await expectNoFatalCrash(page)
      const file = await captureMobileEvidence(
        page,
        item.module,
        item.route.replace(/\//g, '_'),
        vp.name,
      )
      evidenceIndex.push(file)
      const measured = await measureDocumentOverflow(page)
      expect(
        measured.overflowPx,
        `${item.route} overflow ${measured.overflowPx}px`,
      ).toBeLessThanOrEqual(2)
    }
  })
})
