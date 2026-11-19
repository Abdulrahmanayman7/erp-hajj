import { test, expect } from '@playwright/test'

import { expectNoFatalCrash, gotoApp } from '../src/helpers/ui'

/** All static list/create pages under /app (no :id detail routes). */
const routes: string[] = [
  '/app/dashboard',
  '/app/operational-kpis',
  '/app/executive-dashboard',
  '/app/users',
  '/app/roles',
  '/app/organization',
  '/app/organization-tree',
  '/app/employees',
  '/app/pilgrims',
  '/app/seasons',
  '/app/season-groups',
  '/app/season-programs',
  '/app/season-families',
  '/app/season-templates',
  '/app/season-safe-copy',
  '/app/season-copy-runs',
  '/app/season-reminder-rules',
  '/app/contracts',
  '/app/suppliers',
  '/app/supplier-evaluations',
  '/app/rfqs',
  '/app/rfqs/create',
  '/app/purchase-orders',
  '/app/purchase-orders/create',
  '/app/goods-receipts',
  '/app/goods-receipts/create',
  '/app/purchase-requests',
  '/app/purchase-requests/create',
  '/app/purchase-requests/approval-inbox',
  '/app/purchase-demand-groups',
  '/app/purchase-control-reports',
  '/app/purchase-approval-settings',
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
  '/app/gps-providers',
  '/app/gps-devices',
  '/app/gps-fleet-map',
  '/app/notifications',
  '/app/settings',
  '/app/audit',
]

test.describe('Frontend navigation — all list/create pages', () => {
  for (const route of routes) {
    test(`opens ${route}`, async ({ page }) => {
      await gotoApp(page, route)
      await expectNoFatalCrash(page)
      await expect(page).not.toHaveURL(/\/login/)
      const body = await page.locator('main, #app, body').first().innerText()
      expect(body.length, `empty page for ${route}`).toBeGreaterThan(20)
    })
  }
})
