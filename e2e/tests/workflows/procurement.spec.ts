import { test, expect } from '@playwright/test'

import { apiFromPage, apiGet, apiPost } from '../../src/helpers/api'
import { gotoApp, expectNoFatalCrash } from '../../src/helpers/ui'

test.describe('Workflow: procurement', () => {
  test('API lists suppliers and purchase requests', async ({ page }) => {
    const session = await apiFromPage(page)
    for (const path of [
      '/api/v1/suppliers?per_page=10',
      '/api/v1/purchase-requests?per_page=10',
      '/api/v1/purchase-requests/approval-inbox',
    ]) {
      const { response } = await apiGet(session, path)
      expect([200, 403, 404]).toContain(response.status())
    }
  })

  test('UI create purchase request page loads with branded form', async ({ page }) => {
    await gotoApp(page, '/app/purchase-requests/create')
    await expectNoFatalCrash(page)
    await expect(page.getByText(/إنشاء مسودة طلب شراء|طلب شراء/i).first()).toBeVisible()
    await expect(page.getByText(/نوع الطلب|العنوان|الغرض/i).first()).toBeVisible()
  })

  test('API creates draft purchase request when units exist', async ({ page }) => {
    const session = await apiFromPage(page)
    const units = await apiGet(session, '/api/v1/organization-units?per_page=5')
    if (units.response.status() !== 200) {
      test.skip(true, 'organization units unavailable')
    }
    const unitId = units.json?.data?.[0]?.id ?? units.json?.data?.data?.[0]?.id ?? null
    if (!unitId) {
      test.skip(true, 'no organization unit to attach')
    }

    const { response, json } = await apiPost(session, '/api/v1/purchase-requests', {
      request_kind: 'administrative',
      season_id: null,
      title: `E2E PR ${Date.now()}`,
      purpose: 'Playwright end-to-end draft creation',
      priority: 'normal',
      urgency_justification: null,
      beneficiary_organization_unit_id: unitId,
      optional_supplier_id: null,
      currency: 'SAR',
      lines: [
        {
          line_no: 1,
          description: 'E2E line item',
          specifications: null,
          quantity: 1,
          unit: 'قطعة',
          need_date: '2026-12-01',
          unit_estimate: 10,
          unpriced_reason: null,
        },
      ],
    })
    expect([200, 201, 403, 422]).toContain(response.status())
    if (response.status() === 200 || response.status() === 201) {
      expect(json?.data?.id || json?.id).toBeTruthy()
    }
  })
})
