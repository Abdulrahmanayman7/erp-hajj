import { test, expect } from '@playwright/test'

import { apiFromPage, apiGet, apiPost, resolveSeasonId } from '../../src/helpers/api'
import { acceptCreateStatus, acceptReadStatus, firstId, stamp } from '../../src/helpers/data'
import { expectNoFatalCrash, gotoApp } from '../../src/helpers/ui'

test.describe('Workflow: transport & GPS', () => {
  test('API lists transport + GPS endpoints', async ({ page }) => {
    const session = await apiFromPage(page)
    const seasonId = await resolveSeasonId(session)
    for (const path of [
      '/api/v1/transport-vehicles?per_page=10',
      '/api/v1/transport-external-drivers?per_page=10',
      '/api/v1/transport-points?per_page=10',
      '/api/v1/gps/providers',
      '/api/v1/gps/device-bindings',
      '/api/v1/gps/fleet-map',
    ]) {
      const { response } = await apiGet(session, path)
      expect(acceptReadStatus(response.status())).toBeTruthy()
    }
    if (seasonId) {
      for (const path of [
        '/api/v1/transport-season-vehicles?per_page=10',
        '/api/v1/transport-movements?per_page=10',
        '/api/v1/transport-breakdowns?per_page=10',
      ]) {
        const { response } = await apiGet(session, path, { seasonId })
        expect(acceptReadStatus(response.status())).toBeTruthy()
      }
    }
  })

  test('API creates vehicle + point + driver + GPS simulator provider', async ({ page }) => {
    const session = await apiFromPage(page)
    const tag = stamp('TRN')
    const plate = `E2E${String(Date.now()).slice(-6)}`

    const vehicle = await apiPost(session, '/api/v1/transport-vehicles', {
      plate_number: plate,
      vehicle_class: 'bus',
      notes: 'playwright e2e',
    })
    expect(acceptCreateStatus(vehicle.response.status())).toBeTruthy()
    const vehicleId = firstId(vehicle.json)

    const point = await apiPost(session, '/api/v1/transport-points', {
      code: `PT_${String(Date.now()).slice(-8)}`,
      name: `${tag} Point`,
      point_type: 'custom',
      notes: 'e2e',
    })
    expect(acceptCreateStatus(point.response.status())).toBeTruthy()
    const pointId = firstId(point.json)

    const driver = await apiPost(session, '/api/v1/transport-external-drivers', {
      full_name: `${tag} Driver`,
      phone: '+966500000099',
      notes: 'e2e',
    })
    expect(acceptCreateStatus(driver.response.status())).toBeTruthy()
    const driverId = firstId(driver.json)

    const provider = await apiPost(session, '/api/v1/gps/providers', {
      name: `${tag} Simulator`,
      code: `sim_${String(Date.now()).slice(-8)}`,
      connection_method: 'simulator',
      is_enabled: false,
      settings: {
        base_latitude: 21.4225,
        base_longitude: 39.8262,
        radius_km: 5,
        tracking_enabled: false,
      },
    })
    expect(acceptCreateStatus(provider.response.status())).toBeTruthy()
    const providerId = firstId(provider.json)

    if (vehicleId) {
      await gotoApp(page, `/app/transport-vehicles`)
      await expectNoFatalCrash(page)
    }
    if (pointId) {
      await gotoApp(page, '/app/transport-points')
      await expectNoFatalCrash(page)
    }
    if (driverId) {
      await gotoApp(page, '/app/transport-drivers')
      await expectNoFatalCrash(page)
    }
    if (providerId) {
      await gotoApp(page, '/app/gps-providers')
      await expectNoFatalCrash(page)
    }
  })

  test('UI transport + GPS pages', async ({ page }) => {
    for (const path of [
      '/app/transport-vehicles',
      '/app/transport-drivers',
      '/app/transport-season-fleet',
      '/app/transport-points',
      '/app/transport-movements',
      '/app/transport-breakdowns',
      '/app/gps-providers',
      '/app/gps-devices',
      '/app/gps-fleet-map',
    ]) {
      await gotoApp(page, path)
      await expectNoFatalCrash(page)
    }
  })
})
