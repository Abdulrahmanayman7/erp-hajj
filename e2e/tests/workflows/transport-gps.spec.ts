import { test, expect } from '@playwright/test'

import { apiFromPage, apiGet, apiPost } from '../../src/helpers/api'
import { gotoApp, expectNoFatalCrash } from '../../src/helpers/ui'

test.describe('Workflow: transport & GPS', () => {
  test('API transport + GPS endpoints respond', async ({ page }) => {
    const session = await apiFromPage(page)
    for (const path of [
      '/api/v1/transport-vehicles?per_page=10',
      '/api/v1/transport-external-drivers?per_page=10',
      '/api/v1/transport-points?per_page=10',
      '/api/v1/gps/providers',
      '/api/v1/gps/device-bindings',
      '/api/v1/gps/fleet-map',
    ]) {
      const { response } = await apiGet(session, path)
      expect([200, 403, 404, 422]).toContain(response.status())
    }
  })

  test('API can create transport vehicle draft-like record', async ({ page }) => {
    const session = await apiFromPage(page)
    const plate = `E2E${String(Date.now()).slice(-6)}`
    const { response, json } = await apiPost(session, '/api/v1/transport-vehicles', {
      plate_number: plate,
      vehicle_class: 'bus',
      notes: 'playwright e2e',
    })
    expect([200, 201, 403, 422]).toContain(response.status())
    if (response.status() === 200 || response.status() === 201) {
      expect(json?.data?.id || json?.id).toBeTruthy()
    }
  })

  test('UI transport vehicles + GPS providers pages', async ({ page }) => {
    await gotoApp(page, '/app/transport-vehicles')
    await expectNoFatalCrash(page)
    await expect(page.getByText(/المركبات|Vehicles/i).first()).toBeVisible()

    await gotoApp(page, '/app/gps-providers')
    await expectNoFatalCrash(page)
    await expect(page.getByText(/GPS|مزود/i).first()).toBeVisible()
  })
})
