import { test, expect } from '@playwright/test'

import { apiFromPage, apiGet } from '../src/helpers/api'

test.describe('Backend API smoke (authenticated)', () => {
  test('auth/me + core list endpoints via browser session', async ({ page }) => {
    const session = await apiFromPage(page)
    expect(session.user).toBeTruthy()

    const endpoints = [
      '/api/v1/auth/me',
      '/api/v1/dashboard',
      '/api/v1/users?per_page=5',
      '/api/v1/roles?per_page=5',
      '/api/v1/organization-units?per_page=5',
      '/api/v1/employees?per_page=5',
      '/api/v1/seasons?per_page=5',
      '/api/v1/pilgrims?per_page=5',
      '/api/v1/contracts?per_page=5',
      '/api/v1/suppliers?per_page=5',
      '/api/v1/purchase-requests?per_page=5',
      '/api/v1/meetings?per_page=5',
      '/api/v1/decisions?per_page=5',
      '/api/v1/tasks?per_page=5',
      '/api/v1/documents?per_page=5',
      '/api/v1/warehouses?per_page=5',
      '/api/v1/inventory-items?per_page=5',
      '/api/v1/assets?per_page=5',
      '/api/v1/transport-vehicles?per_page=5',
      '/api/v1/transport-points?per_page=5',
      '/api/v1/gps/providers',
      '/api/v1/gps/device-bindings',
      '/api/v1/tenant-settings',
      '/api/v1/notifications?per_page=5',
      '/api/v1/audit-logs?per_page=5',
    ]

    for (const path of endpoints) {
      const { response, json } = await apiGet(session, path)
      expect(
        response.status(),
        `${path} => ${response.status()} ${JSON.stringify(json).slice(0, 240)}`,
      ).toBeLessThan(500)
      expect([200, 403, 404]).toContain(response.status())
    }
  })
})
