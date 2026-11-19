import { test, expect } from '@playwright/test'

import { apiFromPage, apiGet, apiPost } from '../../src/helpers/api'
import { acceptCreateStatus, acceptReadStatus, firstId, stamp } from '../../src/helpers/data'
import { expectNoFatalCrash, gotoApp } from '../../src/helpers/ui'

test.describe('Workflow: procurement cycle', () => {
  test('API lists procurement endpoints', async ({ page }) => {
    const session = await apiFromPage(page)
    for (const path of [
      '/api/v1/suppliers?per_page=10',
      '/api/v1/supplier-evaluations?per_page=10',
      '/api/v1/purchase-requests?per_page=10',
      '/api/v1/purchase-requests/approval-inbox',
      '/api/v1/rfqs?per_page=10',
      '/api/v1/purchase-orders?per_page=10',
      '/api/v1/goods-receipts?per_page=10',
    ]) {
      const { response } = await apiGet(session, path)
      expect(acceptReadStatus(response.status())).toBeTruthy()
    }
  })

  test('API creates supplier + purchase request draft + opens UI', async ({ page }) => {
    const session = await apiFromPage(page)
    const tag = stamp('PRC')

    const supplier = await apiPost(session, '/api/v1/suppliers', {
      display_name: `${tag} Supplier`,
      legal_name: `${tag} Legal`,
      supplier_type: 'company',
      country_code: 'SA',
      notes: 'playwright procurement',
    })
    expect(acceptCreateStatus(supplier.response.status())).toBeTruthy()
    const supplierId = firstId(supplier.json)

    const units = await apiGet(session, '/api/v1/organization-units?per_page=5')
    const unitId = firstId(units.json)
    if (!unitId) {
      test.skip(true, 'no organization unit')
    }

    const pr = await apiPost(session, '/api/v1/purchase-requests', {
      request_kind: 'administrative',
      season_id: null,
      title: `${tag} Purchase Request`,
      purpose: 'Playwright procurement workflow',
      priority: 'normal',
      urgency_justification: null,
      beneficiary_organization_unit_id: unitId,
      optional_supplier_id: supplierId,
      currency: 'SAR',
      lines: [
        {
          line_no: 1,
          description: 'E2E line item',
          specifications: null,
          quantity: 2,
          unit: 'قطعة',
          need_date: '2026-12-01',
          unit_estimate: 25,
          unpriced_reason: null,
        },
      ],
    })
    expect(acceptCreateStatus(pr.response.status())).toBeTruthy()
    const prId = firstId(pr.json)

    if (prId) {
      const submit = await apiPost(session, `/api/v1/purchase-requests/${prId}/submit`, {
        acknowledge_duplicates: true,
      })
      expect([200, 403, 409, 422]).toContain(submit.response.status())
      await gotoApp(page, `/app/purchase-requests/${prId}`)
      await expectNoFatalCrash(page)
    }
    if (supplierId) {
      await gotoApp(page, `/app/suppliers/${supplierId}`)
      await expectNoFatalCrash(page)
    }
  })

  test('UI procurement pages', async ({ page }) => {
    for (const path of [
      '/app/suppliers',
      '/app/supplier-evaluations',
      '/app/purchase-requests',
      '/app/purchase-requests/create',
      '/app/purchase-requests/approval-inbox',
      '/app/purchase-demand-groups',
      '/app/purchase-control-reports',
      '/app/purchase-approval-settings',
      '/app/rfqs',
      '/app/rfqs/create',
      '/app/purchase-orders',
      '/app/purchase-orders/create',
      '/app/goods-receipts',
      '/app/goods-receipts/create',
    ]) {
      await gotoApp(page, path)
      await expectNoFatalCrash(page)
    }
  })
})
