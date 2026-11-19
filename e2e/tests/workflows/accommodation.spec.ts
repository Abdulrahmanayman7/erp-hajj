import { test, expect } from '@playwright/test'

import { apiFromPage, apiGet, apiPost, resolveSeasonId } from '../../src/helpers/api'
import { acceptCreateStatus, acceptReadStatus, firstId, stamp } from '../../src/helpers/data'
import { expectNoFatalCrash, gotoApp } from '../../src/helpers/ui'

test.describe('Workflow: accommodation', () => {
  test('API lists facilities (season-scoped)', async ({ page }) => {
    const session = await apiFromPage(page)
    const seasonId = await resolveSeasonId(session)
    if (!seasonId) {
      test.skip(true, 'no season available')
    }
    const { response } = await apiGet(session, '/api/v1/accommodation/facilities?per_page=10', {
      seasonId,
    })
    expect(acceptReadStatus(response.status())).toBeTruthy()
  })

  test('API creates facility and opens accommodation UI', async ({ page }) => {
    const session = await apiFromPage(page)
    const seasonId = await resolveSeasonId(session)
    if (!seasonId) {
      test.skip(true, 'no season available')
    }
    const tag = stamp('ACC')
    const code = `ACC${String(Date.now()).slice(-6)}`

    const facility = await apiPost(
      session,
      '/api/v1/accommodation/facilities',
      {
        kind: 'hotel',
        code,
        name: `${tag} Hotel`,
        is_active: true,
      },
      { seasonId },
    )
    expect(acceptCreateStatus(facility.response.status())).toBeTruthy()
    const facilityId = firstId(facility.json)

    if (facilityId) {
      const structure = await apiPost(
        session,
        `/api/v1/accommodation/facilities/${facilityId}/structures`,
        { kind: 'building', name: `${tag} Wing` },
        { seasonId },
      )
      expect(
        acceptCreateStatus(structure.response.status()) || [404].includes(structure.response.status()),
      ).toBeTruthy()
    }

    await gotoApp(page, '/app/accommodation')
    await expectNoFatalCrash(page)
  })

  test('UI accommodation pages', async ({ page }) => {
    for (const path of [
      '/app/accommodation',
      '/app/accommodation/allocation',
      '/app/accommodation/drafts',
      '/app/accommodation/scopes',
    ]) {
      await gotoApp(page, path)
      await expectNoFatalCrash(page)
    }
  })
})
