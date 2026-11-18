import { test, expect } from '@playwright/test'

import { expectNoFatalCrash, gotoApp } from '../src/helpers/ui'

const routes: string[] = [
  '/app/dashboard',
  '/app/users',
  '/app/roles',
  '/app/organization',
  '/app/employees',
  '/app/seasons',
  '/app/pilgrims',
  '/app/season-programs',
  '/app/season-families',
  '/app/contracts',
  '/app/suppliers',
  '/app/purchase-requests',
  '/app/purchase-requests/create',
  '/app/meetings',
  '/app/decisions',
  '/app/tasks',
  '/app/documents',
  '/app/warehouses',
  '/app/inventory/items',
  '/app/assets',
  '/app/transport-vehicles',
  '/app/transport-drivers',
  '/app/transport-points',
  '/app/transport-movements',
  '/app/accommodation',
  '/app/gps-providers',
  '/app/gps-devices',
  '/app/season-projects',
  '/app/risks',
  '/app/complaints',
  '/app/notifications',
  '/app/settings',
  '/app/audit',
]

test.describe('Frontend navigation smoke', () => {
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
