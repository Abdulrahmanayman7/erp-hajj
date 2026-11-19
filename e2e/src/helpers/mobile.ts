import { expect, type Page } from '@playwright/test'
import fs from 'node:fs'
import path from 'node:path'

export type MobileViewport = { name: string; width: number; height: number }

export const MOBILE_VIEWPORTS: MobileViewport[] = [
  { name: 'narrow-320', width: 320, height: 640 },
  { name: 'small-360', width: 360, height: 800 },
  { name: 'iphone-375', width: 375, height: 812 },
  { name: 'android-393', width: 393, height: 873 },
  { name: 'large-430', width: 430, height: 932 },
]

export const TABLET_VIEWPORTS: MobileViewport[] = [
  { name: 'tablet-portrait', width: 768, height: 1024 },
  { name: 'tablet-landscape', width: 1024, height: 768 },
]

export const DESKTOP_VIEWPORT: MobileViewport = {
  name: 'desktop-1366',
  width: 1366,
  height: 768,
}

/** Static app routes without :id params (from Vue Router inventory). */
export const STATIC_APP_ROUTES: string[] = [
  '/app',
  '/app/operational-kpis',
  '/app/executive-dashboard',
  '/app/users',
  '/app/roles',
  '/app/organization',
  '/app/organization-tree',
  '/app/employees',
  '/app/seasons',
  '/app/season-groups',
  '/app/season-programs',
  '/app/season-families',
  '/app/pilgrims',
  '/app/season-templates',
  '/app/season-safe-copy',
  '/app/season-copy-runs',
  '/app/season-reminder-rules',
  '/app/contracts',
  '/app/suppliers',
  '/app/supplier-evaluations',
  '/app/rfqs',
  '/app/rfqs/create',
  '/app/purchase-requests',
  '/app/purchase-requests/approval-inbox',
  '/app/purchase-requests/create',
  '/app/purchase-demand-groups',
  '/app/purchase-control-reports',
  '/app/purchase-approval-settings',
  '/app/purchase-orders',
  '/app/purchase-orders/create',
  '/app/goods-receipts',
  '/app/goods-receipts/create',
  '/app/meetings',
  '/app/decisions',
  '/app/tasks',
  '/app/season-projects',
  '/app/risks',
  '/app/complaints',
  '/app/documents',
  '/app/warehouses',
  '/app/inventory',
  '/app/inventory/items',
  '/app/inventory/movements',
  '/app/transport-vehicles',
  '/app/transport-drivers',
  '/app/transport-season-fleet',
  '/app/transport-points',
  '/app/transport-movements',
  '/app/transport-breakdowns',
  '/app/assets',
  '/app/my-custodies',
  '/app/accommodation',
  '/app/accommodation/allocation',
  '/app/accommodation/drafts',
  '/app/accommodation/scopes',
  '/app/notifications',
  '/app/audit',
  '/app/settings',
  '/app/gps-providers',
  '/app/gps-devices',
  '/app/gps-fleet-map',
]

export async function setViewport(page: Page, vp: MobileViewport): Promise<void> {
  await page.setViewportSize({ width: vp.width, height: vp.height })
}

export type OverflowReport = {
  route: string
  viewport: string
  documentScrollWidth: number
  clientWidth: number
  overflowPx: number
  offenders: Array<{ tag: string; className: string; right: number; width: number }>
}

export async function measureDocumentOverflow(page: Page): Promise<{
  documentScrollWidth: number
  clientWidth: number
  overflowPx: number
  offenders: Array<{ tag: string; className: string; right: number; width: number }>
}> {
  return page.evaluate(() => {
    const docEl = document.documentElement
    const body = document.body
    const clientWidth = Math.max(docEl.clientWidth, window.innerWidth)
    const documentScrollWidth = Math.max(docEl.scrollWidth, body.scrollWidth)
    const overflowPx = Math.max(0, documentScrollWidth - clientWidth)
    const offenders: Array<{ tag: string; className: string; right: number; width: number }> = []
    if (overflowPx > 1) {
      const nodes = Array.from(document.querySelectorAll('body *')) as HTMLElement[]
      for (const el of nodes) {
        const rect = el.getBoundingClientRect()
        if (rect.width < 2 || rect.height < 2) continue
        if (rect.right > clientWidth + 1) {
          offenders.push({
            tag: el.tagName.toLowerCase(),
            className: (el.className || '').toString().slice(0, 120),
            right: Math.round(rect.right),
            width: Math.round(rect.width),
          })
          if (offenders.length >= 8) break
        }
      }
    }
    return { documentScrollWidth, clientWidth, overflowPx, offenders }
  })
}

export async function expectNoDocumentOverflow(
  page: Page,
  route: string,
  viewportName: string,
  tolerancePx = 1,
): Promise<OverflowReport> {
  const measured = await measureDocumentOverflow(page)
  const report: OverflowReport = { route, viewport: viewportName, ...measured }
  expect(
    measured.overflowPx,
    `Horizontal overflow on ${route} @ ${viewportName}: ${measured.overflowPx}px; offenders=${JSON.stringify(measured.offenders)}`,
  ).toBeLessThanOrEqual(tolerancePx)
  return report
}

export async function captureMobileEvidence(
  page: Page,
  module: string,
  routeSlug: string,
  viewportName: string,
): Promise<string> {
  const dir = path.join(
    process.cwd(),
    'test-results',
    'mobile-evidence',
    module,
    routeSlug,
  )
  fs.mkdirSync(dir, { recursive: true })
  const file = path.join(dir, `${viewportName}.png`)
  await page.screenshot({ path: file, fullPage: false })
  return file
}
