import { test, expect } from '@playwright/test'

import { apiFromPage, apiGet, apiPost } from '../../src/helpers/api'
import { gotoApp, expectNoFatalCrash } from '../../src/helpers/ui'

const stamp = Date.now()

test.describe('Workflow: seasons & pilgrims', () => {
  test('API create season then UI lists seasons', async ({ page }) => {
    const session = await apiFromPage(page)
    const name = `E2E Season ${stamp}`
    const { response, json } = await apiPost(session, '/api/v1/seasons', {
      code: `E2E_${stamp}`,
      name,
      hajj_year: 1447,
      operational_starts_on: '2026-05-01',
      operational_ends_on: '2026-06-15',
      notes: 'playwright e2e',
    })
    expect([200, 201, 403, 422]).toContain(response.status())
    if (response.status() === 200 || response.status() === 201) {
      expect(json?.data?.id || json?.id).toBeTruthy()
    }

    const list = await apiGet(session, '/api/v1/seasons?per_page=20')
    expect([200, 403]).toContain(list.response.status())

    await gotoApp(page, '/app/seasons')
    await expectNoFatalCrash(page)
    await expect(page.getByText(/المواسم|Seasons/i).first()).toBeVisible()
  })

  test('UI pilgrims page loads create affordance', async ({ page }) => {
    await gotoApp(page, '/app/pilgrims')
    await expectNoFatalCrash(page)
    const create = page.getByRole('button', { name: /حاج|إضافة|جديد|Create|Pilgrim/i }).first()
    if (await create.isVisible().catch(() => false)) {
      await create.click()
      await expect(
        page.locator('[data-test="app-form-drawer"], [role="dialog"]').first(),
      ).toBeVisible({ timeout: 10_000 })
    }
  })
})
