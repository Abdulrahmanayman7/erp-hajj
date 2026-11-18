import { test, expect } from '@playwright/test'

import { apiFromPage, apiGet, apiPost } from '../../src/helpers/api'
import { gotoApp, expectNoFatalCrash } from '../../src/helpers/ui'

test.describe('Workflow: governance chain', () => {
  test('API meetings / decisions / tasks list', async ({ page }) => {
    const session = await apiFromPage(page)
    for (const path of [
      '/api/v1/meetings?per_page=10',
      '/api/v1/decisions?per_page=10',
      '/api/v1/tasks?per_page=10',
      '/api/v1/contracts?per_page=10',
      '/api/v1/documents?per_page=10',
    ]) {
      const { response } = await apiGet(session, path)
      expect([200, 403]).toContain(response.status())
    }
  })

  test('API creates draft meeting when permitted', async ({ page }) => {
    const session = await apiFromPage(page)
    const { response, json } = await apiPost(session, '/api/v1/meetings', {
      title: `E2E Meeting ${Date.now()}`,
      description: 'Playwright governance smoke',
      location_type: 'physical',
      location_text: 'E2E room',
      scheduled_at: '2026-12-01T10:00:00+03:00',
    })
    expect([200, 201, 403, 422]).toContain(response.status())
    if (response.status() === 200 || response.status() === 201) {
      expect(json?.data?.id || json?.id).toBeTruthy()
    }
  })

  test('UI governance pages load', async ({ page }) => {
    for (const path of ['/app/meetings', '/app/decisions', '/app/tasks', '/app/contracts']) {
      await gotoApp(page, path)
      await expectNoFatalCrash(page)
    }
  })
})
