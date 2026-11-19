import { test, expect } from '@playwright/test'

import { apiFromPage, apiGet, apiPost, resolveSeasonId } from '../../src/helpers/api'
import { acceptCreateStatus, acceptReadStatus, firstId, stamp } from '../../src/helpers/data'
import { expectNoFatalCrash, gotoApp } from '../../src/helpers/ui'

test.describe('Workflow: risks & complaints', () => {
  test('API lists risks and complaints (season-scoped)', async ({ page }) => {
    const session = await apiFromPage(page)
    const seasonId = await resolveSeasonId(session)
    if (!seasonId) {
      test.skip(true, 'no season available')
    }
    for (const path of ['/api/v1/risks?per_page=10', '/api/v1/complaints?per_page=10']) {
      const { response } = await apiGet(session, path, { seasonId })
      expect(acceptReadStatus(response.status())).toBeTruthy()
    }
  })

  test('API creates risk + complaint and opens detail pages', async ({ page }) => {
    const session = await apiFromPage(page)
    const seasonId = await resolveSeasonId(session)
    if (!seasonId) {
      test.skip(true, 'no season available')
    }
    const tag = stamp('RSK')

    const risk = await apiPost(
      session,
      '/api/v1/risks',
      {
        title: `${tag} Risk`,
        description: 'Playwright risk workflow',
        likelihood: 2,
        impact: 3,
      },
      { seasonId },
    )
    expect(acceptCreateStatus(risk.response.status())).toBeTruthy()
    const riskId = firstId(risk.json)

    const complaint = await apiPost(
      session,
      '/api/v1/complaints',
      {
        title: `${tag} Complaint`,
        body: 'Playwright complaint workflow body',
        category_code: 'other',
      },
      { seasonId },
    )
    expect(acceptCreateStatus(complaint.response.status())).toBeTruthy()
    const complaintId = firstId(complaint.json)

    if (riskId) {
      const assess = await apiPost(session, `/api/v1/risks/${riskId}/assess`, {
        likelihood: 3,
        impact: 3,
        notes: 'e2e assess',
      }, { seasonId })
      expect([200, 403, 409, 422]).toContain(assess.response.status())
      await gotoApp(page, `/app/risks/${riskId}`)
      await expectNoFatalCrash(page)
    }
    if (complaintId) {
      await gotoApp(page, `/app/complaints/${complaintId}`)
      await expectNoFatalCrash(page)
    }
  })

  test('UI risks and complaints pages', async ({ page }) => {
    for (const path of ['/app/risks', '/app/complaints']) {
      await gotoApp(page, path)
      await expectNoFatalCrash(page)
    }
  })
})
