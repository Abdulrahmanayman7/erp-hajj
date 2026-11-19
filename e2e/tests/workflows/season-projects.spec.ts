import { test, expect } from '@playwright/test'

import { apiFromPage, apiGet, apiPost, resolveSeasonId } from '../../src/helpers/api'
import { acceptCreateStatus, acceptReadStatus, firstId, stamp } from '../../src/helpers/data'
import { expectNoFatalCrash, gotoApp } from '../../src/helpers/ui'

test.describe('Workflow: season projects', () => {
  test('API lists season projects', async ({ page }) => {
    const session = await apiFromPage(page)
    const seasonId = await resolveSeasonId(session)
    if (!seasonId) {
      test.skip(true, 'no season available')
    }
    const { response } = await apiGet(session, '/api/v1/season-projects?per_page=10', { seasonId })
    expect(acceptReadStatus(response.status())).toBeTruthy()
  })

  test('API creates season project and opens detail', async ({ page }) => {
    const session = await apiFromPage(page)
    const seasonId = await resolveSeasonId(session)
    if (!seasonId) {
      test.skip(true, 'no season available')
    }
    const tag = stamp('PRJ')

    const employees = await apiGet(session, '/api/v1/employees?per_page=5&status=active')
    const ownerId = firstId(employees.json)

    const project = await apiPost(
      session,
      '/api/v1/season-projects',
      {
        title: `${tag} Project`,
        description: 'Playwright season project',
        owner_employee_id: ownerId,
        budget_amount: 5000,
        currency_code: 'SAR',
      },
      { seasonId },
    )
    expect(acceptCreateStatus(project.response.status())).toBeTruthy()
    const projectId = firstId(project.json)

    if (projectId) {
      const activate = await apiPost(
        session,
        `/api/v1/season-projects/${projectId}/activate`,
        {},
        { seasonId },
      )
      expect([200, 403, 409, 422]).toContain(activate.response.status())
      await gotoApp(page, `/app/season-projects/${projectId}`)
      await expectNoFatalCrash(page)
    }
  })

  test('UI season projects page', async ({ page }) => {
    await gotoApp(page, '/app/season-projects')
    await expectNoFatalCrash(page)
  })
})
