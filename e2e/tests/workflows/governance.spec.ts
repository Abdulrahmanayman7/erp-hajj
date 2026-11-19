import { test, expect } from '@playwright/test'

import { apiFromPage, apiGet, apiPost } from '../../src/helpers/api'
import { acceptCreateStatus, acceptReadStatus, firstId, stamp } from '../../src/helpers/data'
import { expectNoFatalCrash, gotoApp } from '../../src/helpers/ui'

test.describe('Workflow: governance chain (Meeting → Decision → Task)', () => {
  test('API lists governance entities', async ({ page }) => {
    const session = await apiFromPage(page)
    for (const path of [
      '/api/v1/meetings?per_page=10',
      '/api/v1/decisions?per_page=10',
      '/api/v1/tasks?per_page=10',
      '/api/v1/contracts?per_page=10',
      '/api/v1/documents?per_page=10',
    ]) {
      const { response } = await apiGet(session, path)
      expect(acceptReadStatus(response.status())).toBeTruthy()
    }
  })

  test('API creates meeting → decision → task chain', async ({ page }) => {
    const session = await apiFromPage(page)
    const tag = stamp('GOV')

    const meeting = await apiPost(session, '/api/v1/meetings', {
      title: `${tag} Meeting`,
      description: 'Playwright governance chain',
      location_type: 'physical',
      location_text: 'E2E room',
      scheduled_at: '2026-12-01T10:00:00+03:00',
    })
    expect(acceptCreateStatus(meeting.response.status())).toBeTruthy()
    const meetingId = firstId(meeting.json)

    const decision = await apiPost(session, '/api/v1/decisions', {
      title: `${tag} Decision`,
      body: 'Standalone decision created by Playwright E2E',
      notes: 'governance chain',
    })
    expect(acceptCreateStatus(decision.response.status())).toBeTruthy()
    const decisionId = firstId(decision.json)

    const employees = await apiGet(session, '/api/v1/employees?per_page=5&status=active')
    const employeeId = firstId(employees.json)

    const task = await apiPost(session, '/api/v1/tasks', {
      title: `${tag} Task`,
      description: 'Task linked to decision when possible',
      priority: 'medium',
      decision_id: decisionId,
      assigned_to_employee_id: employeeId,
      due_date: '2026-12-15',
    })
    expect(acceptCreateStatus(task.response.status())).toBeTruthy()
    const taskId = firstId(task.json)

    if (meetingId) {
      await gotoApp(page, `/app/meetings/${meetingId}`)
      await expectNoFatalCrash(page)
    }
    if (decisionId) {
      await gotoApp(page, `/app/decisions/${decisionId}`)
      await expectNoFatalCrash(page)
    }
    if (taskId) {
      await gotoApp(page, `/app/tasks/${taskId}`)
      await expectNoFatalCrash(page)
    }
  })

  test('API advances decision submit when draft exists', async ({ page }) => {
    const session = await apiFromPage(page)
    const created = await apiPost(session, '/api/v1/decisions', {
      title: `${stamp('DEC')} Draft`,
      body: 'Submit transition smoke',
    })
    const id = firstId(created.json)
    if (!id) {
      test.skip(true, 'decision create blocked')
    }
    const submit = await apiPost(session, `/api/v1/decisions/${id}/submit`, {})
    expect([200, 403, 409, 422]).toContain(submit.response.status())
  })

  test('UI governance pages + create affordances', async ({ page }) => {
    for (const path of ['/app/meetings', '/app/decisions', '/app/tasks', '/app/contracts', '/app/documents']) {
      await gotoApp(page, path)
      await expectNoFatalCrash(page)
    }
  })
})
