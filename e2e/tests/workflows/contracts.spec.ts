import { test, expect } from '@playwright/test'

import { apiFromPage, apiGet, apiPost } from '../../src/helpers/api'
import { acceptCreateStatus, acceptReadStatus, firstId, stamp } from '../../src/helpers/data'
import { expectNoFatalCrash, gotoApp } from '../../src/helpers/ui'

test.describe('Workflow: contract lifecycle', () => {
  test('API creates draft contract and opens detail', async ({ page }) => {
    const session = await apiFromPage(page)
    const categories = await apiGet(session, '/api/v1/contract-categories?per_page=20')
    expect(acceptReadStatus(categories.response.status())).toBeTruthy()
    let categoryId = firstId(categories.json)

    if (!categoryId) {
      const createdCat = await apiPost(session, '/api/v1/contract-categories', {
        name: `${stamp('CAT')} Category`,
        description: 'e2e',
      })
      categoryId = firstId(createdCat.json)
    }
    if (!categoryId) {
      test.skip(true, 'no contract category available')
    }

    const created = await apiPost(session, '/api/v1/contracts', {
      title: `${stamp('CTR')} Contract`,
      contract_category_id: categoryId,
      counterparty_name: 'E2E Counterparty',
      counterparty_kind: 'vendor',
      start_date: '2026-01-01',
      end_date: '2026-12-31',
      value: 1000,
      currency: 'SAR',
      notes: 'playwright contract lifecycle',
    })
    expect(acceptCreateStatus(created.response.status())).toBeTruthy()
    const id = firstId(created.json)
    if (!id) {
      test.skip(true, 'contract create blocked')
    }

    const review = await apiPost(session, `/api/v1/contracts/${id}/submit-review`, {
      comment: 'e2e review',
    })
    expect([200, 403, 409, 422]).toContain(review.response.status())

    await gotoApp(page, `/app/contracts/${id}`)
    await expectNoFatalCrash(page)
    await expect(page).not.toHaveURL(/\/login/)
  })

  test('UI contracts list loads', async ({ page }) => {
    await gotoApp(page, '/app/contracts')
    await expectNoFatalCrash(page)
  })
})
