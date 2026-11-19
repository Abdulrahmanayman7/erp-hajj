import { test, expect } from '@playwright/test'

import { apiFromPage, apiGet, apiPost } from '../../src/helpers/api'
import { acceptCreateStatus, acceptReadStatus, firstId, stamp } from '../../src/helpers/data'
import { expectNoFatalCrash, gotoApp } from '../../src/helpers/ui'

test.describe('Workflow: HR & organization', () => {
  test('API lists org units + employees + users + roles', async ({ page }) => {
    const session = await apiFromPage(page)
    for (const path of [
      '/api/v1/organization-tree',
      '/api/v1/organization-units?per_page=10',
      '/api/v1/employees?per_page=10',
      '/api/v1/users?per_page=10',
      '/api/v1/roles?per_page=10',
    ]) {
      const { response } = await apiGet(session, path)
      expect(acceptReadStatus(response.status())).toBeTruthy()
    }
  })

  test('API creates org unit + employee when unit exists', async ({ page }) => {
    const session = await apiFromPage(page)
    const tag = stamp('HR')
    const code = `E2E${String(Date.now()).slice(-6)}`

    const parents = await apiGet(session, '/api/v1/organization-units?per_page=5&view=flat&status=active')
    const parentId = firstId(parents.json)

    const unit = await apiPost(session, '/api/v1/organization-units', {
      name: `${tag} Unit`,
      code,
      type: parentId ? 'section' : 'department',
      parent_id: parentId,
      status: 'active',
    })
    expect(acceptCreateStatus(unit.response.status())).toBeTruthy()
    let unitId = firstId(unit.json)
    if (!unitId) {
      unitId = parentId
    }
    if (!unitId) {
      test.skip(true, 'no organization unit available')
    }

    const employee = await apiPost(session, '/api/v1/employees', {
      full_name: `${tag} Employee`,
      organization_unit_id: unitId,
      phone: '+966511111111',
      email: `e2e.${Date.now()}@example.invalid`,
      hire_date: '2026-01-01',
      notes: 'playwright hr',
    })
    expect(acceptCreateStatus(employee.response.status())).toBeTruthy()
    const employeeId = firstId(employee.json)

    if (employeeId) {
      await gotoApp(page, '/app/employees')
      await expectNoFatalCrash(page)
    }
  })

  test('UI org / employees / users / roles pages', async ({ page }) => {
    for (const path of [
      '/app/organization',
      '/app/organization-tree',
      '/app/employees',
      '/app/users',
      '/app/roles',
    ]) {
      await gotoApp(page, path)
      await expectNoFatalCrash(page)
    }
  })
})
