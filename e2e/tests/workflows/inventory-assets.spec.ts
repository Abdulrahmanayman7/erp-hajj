import { test, expect } from '@playwright/test'

import { apiFromPage, apiGet, apiPost } from '../../src/helpers/api'
import { acceptCreateStatus, acceptReadStatus, firstId, stamp } from '../../src/helpers/data'
import { expectNoFatalCrash, gotoApp } from '../../src/helpers/ui'

test.describe('Workflow: inventory & assets', () => {
  test('API lists warehouses / items / assets / balances / custodies', async ({ page }) => {
    const session = await apiFromPage(page)
    for (const path of [
      '/api/v1/warehouses?per_page=10',
      '/api/v1/inventory-categories?per_page=10',
      '/api/v1/inventory-items?per_page=10',
      '/api/v1/inventory/balances?per_page=10',
      '/api/v1/inventory/movements?per_page=10',
      '/api/v1/assets?per_page=10',
      '/api/v1/asset-custodies?per_page=10',
      '/api/v1/my-custodies?per_page=10',
    ]) {
      const { response } = await apiGet(session, path)
      expect(acceptReadStatus(response.status())).toBeTruthy()
    }
  })

  test('API creates warehouse → item → asset and opens details', async ({ page }) => {
    const session = await apiFromPage(page)
    const tag = stamp('INV')

    const warehouse = await apiPost(session, '/api/v1/warehouses', {
      name: `${tag} Warehouse`,
      location: 'E2E location',
      notes: 'playwright inventory',
      is_active: true,
    })
    expect(acceptCreateStatus(warehouse.response.status())).toBeTruthy()
    const warehouseId = firstId(warehouse.json)

    const item = await apiPost(session, '/api/v1/inventory-items', {
      name: `${tag} Item`,
      unit: 'piece',
      minimum_stock: 1,
      notes: 'e2e',
      is_active: true,
    })
    expect(acceptCreateStatus(item.response.status())).toBeTruthy()
    const itemId = firstId(item.json)

    const asset = await apiPost(session, '/api/v1/assets', {
      name: `${tag} Asset`,
      condition: 'good',
      warehouse_id: warehouseId,
      purchase_value: 100,
      acquisition_date: '2026-01-15',
      notes: 'e2e asset',
    })
    expect(acceptCreateStatus(asset.response.status())).toBeTruthy()
    const assetId = firstId(asset.json)

    if (warehouseId) {
      await gotoApp(page, `/app/warehouses/${warehouseId}`)
      await expectNoFatalCrash(page)
    }
    if (itemId) {
      await gotoApp(page, `/app/inventory/items/${itemId}`)
      await expectNoFatalCrash(page)
    }
    if (assetId) {
      await gotoApp(page, `/app/assets/${assetId}`)
      await expectNoFatalCrash(page)
    }
  })

  test('UI inventory and assets pages', async ({ page }) => {
    for (const path of [
      '/app/warehouses',
      '/app/inventory',
      '/app/inventory/items',
      '/app/inventory/movements',
      '/app/assets',
      '/app/my-custodies',
    ]) {
      await gotoApp(page, path)
      await expectNoFatalCrash(page)
    }
  })
})
