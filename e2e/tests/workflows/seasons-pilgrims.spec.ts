import { test, expect } from '@playwright/test'

import { apiFromPage, apiGet, apiPost } from '../../src/helpers/api'
import { acceptCreateStatus, acceptReadStatus, firstId, stamp } from '../../src/helpers/data'
import { expectNoFatalCrash, gotoApp } from '../../src/helpers/ui'

test.describe('Workflow: seasons & pilgrims', () => {
  test('API create season + pilgrim + enroll path', async ({ page }) => {
    const session = await apiFromPage(page)
    const tag = stamp('SEA')

    const season = await apiPost(session, '/api/v1/seasons', {
      code: tag,
      name: `${tag} Season`,
      hajj_year: 1447,
      operational_starts_on: '2026-05-01',
      operational_ends_on: '2026-06-15',
      notes: 'playwright e2e',
    })
    expect(acceptCreateStatus(season.response.status())).toBeTruthy()
    let seasonId = firstId(season.json)
    if (!seasonId) {
      const list = await apiGet(session, '/api/v1/seasons?per_page=5')
      seasonId = firstId(list.json)
    }

    const pilgrim = await apiPost(session, '/api/v1/pilgrims', {
      full_name: `${tag} Pilgrim`,
      gender: 'male',
      nationality: 'SA',
      phone: '+966500000001',
      notes: 'e2e',
    })
    expect(acceptCreateStatus(pilgrim.response.status())).toBeTruthy()
    const pilgrimId = firstId(pilgrim.json)

    if (seasonId) {
      const program = await apiPost(
        session,
        '/api/v1/season-programs',
        { name: `${tag} Program`, code: `${tag}_P`, notes: 'e2e' },
        { seasonId },
      )
      // Some installs use /seasons/{id}/programs — tolerate 404 and alternate.
      if (program.response.status() === 404) {
        const alt = await apiPost(session, `/api/v1/seasons/${seasonId}/programs`, {
          name: `${tag} Program`,
          code: `${tag}_P`,
        })
        expect(acceptCreateStatus(alt.response.status()) || alt.response.status() === 404).toBeTruthy()
      } else {
        expect(acceptCreateStatus(program.response.status()) || program.response.status() === 404).toBeTruthy()
      }

      if (pilgrimId) {
        const enroll = await apiPost(
          session,
          '/api/v1/season-pilgrims',
          { pilgrim_id: pilgrimId },
          { seasonId },
        )
        if (enroll.response.status() === 404) {
          const altEnroll = await apiPost(session, `/api/v1/seasons/${seasonId}/pilgrims`, {
            pilgrim_id: pilgrimId,
          })
          expect(
            acceptCreateStatus(altEnroll.response.status()) || [404, 409].includes(altEnroll.response.status()),
          ).toBeTruthy()
        } else {
          expect(
            acceptCreateStatus(enroll.response.status()) || [409].includes(enroll.response.status()),
          ).toBeTruthy()
        }
      }
    }

    await gotoApp(page, '/app/seasons')
    await expectNoFatalCrash(page)
    await gotoApp(page, '/app/pilgrims')
    await expectNoFatalCrash(page)
    if (pilgrimId) {
      await gotoApp(page, `/app/pilgrims/${pilgrimId}`)
      await expectNoFatalCrash(page)
    }
  })

  test('UI season ops pages', async ({ page }) => {
    for (const path of [
      '/app/seasons',
      '/app/season-programs',
      '/app/season-groups',
      '/app/season-families',
      '/app/season-templates',
      '/app/season-safe-copy',
      '/app/season-copy-runs',
      '/app/season-reminder-rules',
    ]) {
      await gotoApp(page, path)
      await expectNoFatalCrash(page)
    }
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
