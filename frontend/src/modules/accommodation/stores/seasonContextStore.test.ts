import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it } from 'vitest'

import { useAccommodationSeasonStore } from './seasonContextStore'

describe('useAccommodationSeasonStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    sessionStorage.clear()
  })

  it('fails closed for writes until season context is applied', () => {
    const store = useAccommodationSeasonStore()
    expect(store.isWritable).toBe(false)
    expect(store.canWrite).toBe(false)

    store.applyContext({
      season: {
        id: 1,
        code: 'H1447',
        name: 'موسم',
        hajj_year: 1447,
        status: 'active',
        operational_starts_on: '2026-05-01',
        operational_ends_on: '2026-06-01',
      },
      selection: 'default_active',
      is_writable: true,
    })
    expect(store.canWrite).toBe(true)

    store.clearSeasonDetails()
    expect(store.isWritable).toBe(false)
    expect(store.canWrite).toBe(false)
  })

  it('treats closed season as non-writable even when is_writable is true', () => {
    const store = useAccommodationSeasonStore()
    store.applyContext({
      season: {
        id: 2,
        code: 'H1446',
        name: 'مغلق',
        hajj_year: 1446,
        status: 'closed',
        operational_starts_on: '2025-05-01',
        operational_ends_on: '2025-06-01',
      },
      selection: 'explicit',
      is_writable: true,
    })
    expect(store.isClosed).toBe(true)
    expect(store.canWrite).toBe(false)
    expect(store.canConfirm).toBe(false)
    expect(store.canOps).toBe(false)
  })

  it('allows confirm and ops only for Active seasons', () => {
    const store = useAccommodationSeasonStore()
    store.applyContext({
      season: {
        id: 3,
        code: 'H1448',
        name: 'مسودة',
        hajj_year: 1448,
        status: 'draft',
        operational_starts_on: '2026-05-01',
        operational_ends_on: '2026-06-01',
      },
      selection: 'explicit',
      is_writable: true,
    })
    expect(store.canWrite).toBe(true)
    expect(store.canConfirm).toBe(false)
    expect(store.canOps).toBe(false)

    store.applyContext({
      season: {
        id: 4,
        code: 'H1449',
        name: 'نشط',
        hajj_year: 1449,
        status: 'active',
        operational_starts_on: '2026-05-01',
        operational_ends_on: '2026-06-01',
      },
      selection: 'explicit',
      is_writable: true,
    })
    expect(store.canConfirm).toBe(true)
    expect(store.canOps).toBe(true)
  })
})
