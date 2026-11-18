import { test, expect } from '@playwright/test'

import { apiFromPage, apiGet } from '../../src/helpers/api'
import { gotoApp, expectNoFatalCrash } from '../../src/helpers/ui'

test.describe('Workflow: inventory & assets', () => {
  test('API warehouses / items / assets / balances', async ({ page }) => {
    const session = await apiFromPage(page)
    for (const path of [
      '/api/v1/warehouses?per_page=10',
      '/api/v1/inventory-items?per_page=10',
      '/api/v1/inventory/balances?per_page=10',
      '/api/v1/inventory/movements?per_page=10',
      '/api/v1/assets?per_page=10',
    ]) {
      const { response } = await apiGet(session, path)
      expect([200, 403, 404, 422]).toContain(response.status())
    }
  })

  test('UI inventory and assets pages', async ({ page }) => {
    for (const path of [
      '/app/warehouses',
      '/app/inventory/items',
      '/app/inventory/movements',
      '/app/assets',
      '/app/accommodation',
    ]) {
      await gotoApp(page, path)
      await expectNoFatalCrash(page)
    }
  })
})
