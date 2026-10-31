import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it } from 'vitest'
import { nextTick } from 'vue'

import { useAccommodationSeasonStore } from './seasonContextStore'
import type { AccommodationSeason } from '../types/accommodation'

function season(id: number, status: AccommodationSeason['status'] = 'active'): AccommodationSeason {
  return {
    id,
    code: `H${id}`,
    name: `موسم ${id}`,
    hajj_year: 1440 + id,
    status,
    operational_starts_on: '2026-05-01',
    operational_ends_on: '2026-06-01',
  }
}

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
      season: season(3, 'draft'),
      selection: 'explicit',
      is_writable: true,
    })
    expect(store.canWrite).toBe(true)
    expect(store.canConfirm).toBe(false)
    expect(store.canOps).toBe(false)

    store.applyContext({
      season: season(4, 'active'),
      selection: 'explicit',
      is_writable: true,
    })
    expect(store.canConfirm).toBe(true)
    expect(store.canOps).toBe(true)
  })

  it('clears season details immediately when the user switches seasons manually', () => {
    const store = useAccommodationSeasonStore()
    store.applyContext({
      season: season(10),
      selection: 'default_active',
      is_writable: true,
    })
    expect(store.season?.id).toBe(10)

    store.setSelectedSeasonId(11)
    expect(store.selectedSeasonId).toBe(11)
    expect(store.season).toBeNull()
    expect(store.isWritable).toBe(false)
    expect(store.selectionMode).toBe('none')
  })

  it('rejects stale applyContext payloads from a previous context generation', () => {
    const store = useAccommodationSeasonStore()
    store.setSelectedSeasonId(1)
    const stale = store.contextGeneration
    store.setSelectedSeasonId(2)
    const current = store.contextGeneration

    store.applyContext(
      { season: season(1), selection: 'explicit', is_writable: true },
      stale,
    )
    expect(store.selectedSeasonId).toBe(2)
    expect(store.season).toBeNull()

    store.applyContext(
      { season: season(2), selection: 'explicit', is_writable: true },
      current,
    )
    expect(store.selectedSeasonId).toBe(2)
    expect(store.season?.id).toBe(2)
    expect(store.isWritable).toBe(true)
  })

  it('keeps explicit context details after Vue nextTick', async () => {
    const store = useAccommodationSeasonStore()

    store.applyContext({
      season: season(7),
      selection: 'explicit',
      is_writable: true,
    })
    await nextTick()

    expect(store.selectedSeasonId).toBe(7)
    expect(store.season).toEqual(season(7))
    expect(store.selectionMode).toBe('explicit')
    expect(store.isWritable).toBe(true)
  })
})
