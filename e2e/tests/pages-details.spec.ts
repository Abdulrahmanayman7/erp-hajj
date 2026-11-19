import { test, expect } from '@playwright/test'

import { apiFromPage, apiGet, resolveSeasonId } from '../src/helpers/api'
import { acceptReadStatus, firstId } from '../src/helpers/data'
import { expectNoFatalCrash, gotoApp } from '../src/helpers/ui'

const detailTargets: Array<{
  listApi: string
  path: (id: number) => string
  label: string
  seasonScoped?: boolean
}> = [
  { listApi: '/api/v1/pilgrims?per_page=1', path: (id) => `/app/pilgrims/${id}`, label: 'pilgrim' },
  { listApi: '/api/v1/employees?per_page=1', path: () => `/app/employees`, label: 'employees-list' },
  { listApi: '/api/v1/users?per_page=1', path: () => `/app/users`, label: 'users-list' },
  { listApi: '/api/v1/contracts?per_page=1', path: (id) => `/app/contracts/${id}`, label: 'contract' },
  { listApi: '/api/v1/suppliers?per_page=1', path: (id) => `/app/suppliers/${id}`, label: 'supplier' },
  {
    listApi: '/api/v1/purchase-requests?per_page=1',
    path: (id) => `/app/purchase-requests/${id}`,
    label: 'purchase-request',
  },
  { listApi: '/api/v1/meetings?per_page=1', path: (id) => `/app/meetings/${id}`, label: 'meeting' },
  { listApi: '/api/v1/decisions?per_page=1', path: (id) => `/app/decisions/${id}`, label: 'decision' },
  { listApi: '/api/v1/tasks?per_page=1', path: (id) => `/app/tasks/${id}`, label: 'task' },
  { listApi: '/api/v1/documents?per_page=1', path: (id) => `/app/documents/${id}`, label: 'document' },
  { listApi: '/api/v1/warehouses?per_page=1', path: (id) => `/app/warehouses/${id}`, label: 'warehouse' },
  {
    listApi: '/api/v1/inventory-items?per_page=1',
    path: (id) => `/app/inventory/items/${id}`,
    label: 'inventory-item',
  },
  { listApi: '/api/v1/assets?per_page=1', path: (id) => `/app/assets/${id}`, label: 'asset' },
  { listApi: '/api/v1/roles?per_page=1', path: (id) => `/app/roles/${id}/permissions`, label: 'role-permissions' },
  {
    listApi: '/api/v1/season-projects?per_page=1',
    path: (id) => `/app/season-projects/${id}`,
    label: 'season-project',
    seasonScoped: true,
  },
  { listApi: '/api/v1/risks?per_page=1', path: (id) => `/app/risks/${id}`, label: 'risk', seasonScoped: true },
  {
    listApi: '/api/v1/complaints?per_page=1',
    path: (id) => `/app/complaints/${id}`,
    label: 'complaint',
    seasonScoped: true,
  },
  {
    listApi: '/api/v1/supplier-evaluations?per_page=1',
    path: (id) => `/app/supplier-evaluations/${id}`,
    label: 'supplier-evaluation',
  },
  { listApi: '/api/v1/rfqs?per_page=1', path: (id) => `/app/rfqs/${id}`, label: 'rfq' },
  {
    listApi: '/api/v1/purchase-orders?per_page=1',
    path: (id) => `/app/purchase-orders/${id}`,
    label: 'purchase-order',
  },
  {
    listApi: '/api/v1/goods-receipts?per_page=1',
    path: (id) => `/app/goods-receipts/${id}`,
    label: 'goods-receipt',
  },
  {
    listApi: '/api/v1/transport-vehicles?per_page=1',
    path: (id) => `/app/transport-vehicles`,
    label: 'transport-vehicles-list',
  },
  {
    listApi: '/api/v1/transport-movements?per_page=1',
    path: (id) => `/app/transport-movements/${id}`,
    label: 'transport-movement',
    seasonScoped: true,
  },
  {
    listApi: '/api/v1/accommodation/facilities?per_page=1',
    path: () => `/app/accommodation`,
    label: 'accommodation-facilities',
    seasonScoped: true,
  },
  { listApi: '/api/v1/audit-logs?per_page=1', path: (id) => `/app/audit/${id}`, label: 'audit' },
]

test.describe('Frontend detail pages', () => {
  for (const target of detailTargets) {
    test(`opens ${target.label} detail when data exists`, async ({ page }) => {
      const session = await apiFromPage(page)
      const seasonId = target.seasonScoped ? await resolveSeasonId(session) : null
      if (target.seasonScoped && !seasonId) {
        test.skip(true, `no season for ${target.label}`)
      }
      const list = await apiGet(session, target.listApi, {
        seasonId: seasonId ?? undefined,
      })
      expect(acceptReadStatus(list.response.status())).toBeTruthy()
      const id = firstId(list.json)
      if (!id) {
        test.skip(true, `no ${target.label} rows`)
      }
      await gotoApp(page, target.path(id!))
      await expectNoFatalCrash(page)
      await expect(page).not.toHaveURL(/\/login/)
    })
  }
})
