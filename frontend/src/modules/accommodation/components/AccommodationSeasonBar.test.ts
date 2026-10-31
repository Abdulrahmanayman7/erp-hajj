import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query'
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createApp, h, nextTick } from 'vue'
import { createI18n } from 'vue-i18n'

import type { AccommodationSeason, SeasonContextPayload } from '../types/accommodation'
import { useAccommodationSeasonStore } from '../stores/seasonContextStore'
import AccommodationSeasonBar from './AccommodationSeasonBar.vue'

const fetchSeasonContext = vi.fn<() => Promise<SeasonContextPayload>>()
const listSeasonsForAccommodation = vi.fn()

vi.mock('../api/seasonLookupsApi', () => ({
  fetchSeasonContext: (...args: unknown[]) => fetchSeasonContext(...(args as [])),
  listSeasonsForAccommodation: (...args: unknown[]) => listSeasonsForAccommodation(...(args as [])),
}))

vi.mock('@/shared/composables/usePermissions', () => ({
  usePermissions: () => ({
    can: (permission: string) => permission === 'seasons.view',
  }),
}))

function season(id: number, name = `موسم ${id}`): AccommodationSeason {
  return {
    id,
    code: `H${id}`,
    name,
    hajj_year: 1440 + id,
    status: 'active',
    operational_starts_on: '2027-05-01',
    operational_ends_on: '2027-06-30',
  }
}

function explicitContext(id: number, writable = true): SeasonContextPayload {
  return {
    season: season(id),
    selection: 'explicit',
    is_writable: writable,
  }
}

const cleanups: Array<() => void> = []

afterEach(() => {
  cleanups.splice(0).forEach((cleanup) => cleanup())
  fetchSeasonContext.mockReset()
  listSeasonsForAccommodation.mockReset()
  sessionStorage.clear()
})

beforeEach(() => {
  setActivePinia(createPinia())
  sessionStorage.clear()
  listSeasonsForAccommodation.mockResolvedValue({
    data: [season(7, 'موسم سابع'), season(8, 'موسم ثامن')],
    meta: { current_page: 1, per_page: 100, total: 2, last_page: 1 },
  })
})

function mountSeasonBar() {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false, gcTime: 0 } },
  })
  const pinia = createPinia()
  setActivePinia(pinia)
  const host = document.createElement('div')
  document.body.appendChild(host)
  const app = createApp({
    render: () => h(AccommodationSeasonBar),
  })
  app.use(pinia)
  app.use(VueQueryPlugin, { queryClient })
  app.use(
    createI18n({
      legacy: false,
      locale: 'ar',
      messages: {
        ar: {
          accommodation: {
            season: {
              title: 'الموسم الحالي',
              select: 'اختيار الموسم',
              defaultActive: 'الموسم النشط الافتراضي',
              loading: 'جاري تحميل الموسم…',
              refreshing: 'جاري تحديث سياق الموسم…',
              contextError: 'تعذر تحميل سياق الموسم',
              closedNotice: 'الموسم مغلق',
              noListPermission: 'لا صلاحية',
              usingDefault: 'افتراضي',
              unknown: 'غير معروف',
              status: { active: 'نشط', draft: 'مسودة', closed: 'مغلق' },
            },
          },
        },
      },
    }),
  )
  app.mount(host)
  cleanups.push(() => {
    app.unmount()
    host.remove()
    queryClient.clear()
  })
  return { host, queryClient, store: useAccommodationSeasonStore() }
}

describe('AccommodationSeasonBar season-context hydration', () => {
  it('keeps explicit season details after applyContext and the selectedSeasonId watcher flush', async () => {
    fetchSeasonContext.mockResolvedValue(explicitContext(7))
    const { host, store } = mountSeasonBar()

    await vi.waitFor(() => expect(fetchSeasonContext).toHaveBeenCalled())
    await vi.waitFor(() => expect(store.selectedSeasonId).toBe(7))
    await nextTick()
    await nextTick()

    // Regression: AccommodationSeasonBar watches selectedSeasonId and calls
    // clearSeasonDetails(). Explicit applyContext must not lose details after flush.
    expect(store.season).toEqual(season(7))
    expect(store.selectionMode).toBe('explicit')
    expect(store.isWritable).toBe(true)
    expect(host.textContent).toContain('موسم 7')
    expect(host.textContent).not.toMatch(/جاري تحميل الموسم/)
  })

  it('clears stale details on manual season switch until the new context arrives', async () => {
    fetchSeasonContext.mockResolvedValue(explicitContext(7))
    const { host, store } = mountSeasonBar()
    await vi.waitFor(() => expect(store.season?.id).toBe(7))

    let resolveNext!: (payload: SeasonContextPayload) => void
    fetchSeasonContext.mockImplementation(
      () =>
        new Promise<SeasonContextPayload>((resolve) => {
          resolveNext = resolve
        }),
    )

    store.setSelectedSeasonId(8)
    await nextTick()

    expect(store.selectedSeasonId).toBe(8)
    expect(store.season).toBeNull()
    expect(store.selectionMode).toBe('none')
    expect(store.isWritable).toBe(false)
    expect(host.textContent).toMatch(/جاري تحميل الموسم|جاري تحديث سياق الموسم/)

    resolveNext(explicitContext(8, true))
    await vi.waitFor(() => expect(store.season?.id).toBe(8))
    await nextTick()
    await nextTick()

    expect(store.season).toEqual(season(8))
    expect(host.textContent).toContain('موسم 8')
  })

  it('ignores a stale context response from a previous season generation', async () => {
    const store = useAccommodationSeasonStore()

    store.setSelectedSeasonId(1)
    const staleGeneration = store.contextGeneration
    await nextTick()

    store.setSelectedSeasonId(2)
    const currentGeneration = store.contextGeneration
    expect(currentGeneration).toBeGreaterThan(staleGeneration)
    expect(store.season).toBeNull()

    store.applyContext(explicitContext(1), staleGeneration)
    await nextTick()
    expect(store.season).toBeNull()
    expect(store.selectedSeasonId).toBe(2)

    store.applyContext(explicitContext(2), currentGeneration)
    await nextTick()
    // Desired: current season details stick after watcher flush.
    expect(store.selectedSeasonId).toBe(2)
    expect(store.season).toEqual(season(2))
  })

  it('does not leave the bar stuck in loading after an unsuccessful context request', async () => {
    fetchSeasonContext.mockRejectedValue(new Error('context failed'))
    const { host, store } = mountSeasonBar()

    await vi.waitFor(() => expect(fetchSeasonContext).toHaveBeenCalled())
    await vi.waitFor(() => expect(host.textContent).toContain('تعذر تحميل سياق الموسم'))

    expect(store.season).toBeNull()
    expect(store.isWritable).toBe(false)
    expect(host.textContent).not.toContain('جاري تحديث سياق الموسم…')
  })
})
