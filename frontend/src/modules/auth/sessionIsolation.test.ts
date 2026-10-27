import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { QueryClient } from '@tanstack/vue-query'
import { createPinia, setActivePinia } from 'pinia'
import { clearSession, installSessionIsolation } from './sessionIsolation'
import { useAccommodationSeasonStore } from '@/modules/accommodation/stores/seasonContextStore'
import { apiGet } from '@/shared/api/http'
import { sessionGeneration, sessionTransition } from '@/shared/api/sessionLifetime'
import { useToast } from '@/shared/composables/useToast'
import { useConfirm } from '@/shared/composables/useConfirm'

const context = (id: number) => ({ season: { id, code: 'S', name: 'Season', hajj_year: 1448, status: 'active' as const, operational_starts_on: '2027-01-01', operational_ends_on: '2027-12-31' }, selection: 'explicit' as const, is_writable: true })
const deferred = <T>() => { let resolve!: (value: T) => void; const promise = new Promise<T>(r => { resolve = r }); return { promise, resolve } }

describe('session isolation', () => {
  beforeEach(() => { setActivePinia(createPinia()); sessionStorage.clear() })
  afterEach(() => { vi.unstubAllGlobals(); sessionTransition.value = false })

  it.each([
    [{ id: 1, tenant: 10, permissions: ['manage'] }, { id: 2, tenant: 20, permissions: ['view'] }],
    [{ id: 1, tenant: 10, permissions: ['manage'] }, { id: 2, tenant: 10, permissions: ['view'] }],
  ])('evicts all data and season state between accounts %j and %j', async (first, second) => {
    const client = new QueryClient()
    client.setQueryData(['auth', 'me'], first)
    client.setQueryData(['users'], ['private'])
    client.setQueryData(['accommodation', 'assignments', 1], ['private stay'])
    const store = useAccommodationSeasonStore()
    store.applyContext(context(1))
    const oldContext = store.contextGeneration
    const oldSession = sessionGeneration.value
    const toast = useToast()
    toast.success('private name')
    const confirmation = useConfirm().confirm({ title: 'private', message: 'name' })
    await clearSession(client)
    client.setQueryData(['auth', 'me'], second)
    expect(client.getQueryData(['users'])).toBeUndefined()
    expect(client.getQueryData(['accommodation', 'assignments', 1])).toBeUndefined()
    expect(client.getQueryData(['auth', 'me'])).toEqual(second)
    expect(store.selectedSeasonId).toBeNull()
    expect(store.season).toBeNull()
    expect(store.canWrite).toBe(false)
    expect(sessionStorage.getItem('erp-hajj.accommodation.seasonId')).toBeNull()
    store.applyContext(context(1), oldContext, oldSession)
    expect(store.season).toBeNull()
    toast.error('late private name')
    expect(useToast().toasts.value).toEqual([])
    expect(await confirmation).toBe(false)
    client.clear()
  })

  it('cancels an old query and rejects a late response even when fetch ignores abort', async () => {
    const pending = deferred<Response>()
    const fetcher = vi.fn((_input: unknown, _init?: RequestInit) => pending.promise)
    vi.stubGlobal('fetch', fetcher)
    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
    const result = client.fetchQuery({ queryKey: ['private'], queryFn: () => apiGet('/api/v1/private') }).catch(e => e)
    const signal = fetcher.mock.calls[0]?.[1]?.signal
    await clearSession(client)
    const newUser = { id: 2, tenant: { id: 20 }, permissions: ['view'] }
    client.setQueryData(['auth', 'me'], newUser)
    pending.resolve(new Response(JSON.stringify({ success: true, data: 'old' })))
    await result
    expect(client.getQueryData(['private'])).toBeUndefined()
    expect(signal?.aborted).toBe(true)
    expect(client.getQueryData(['auth', 'me'])).toEqual(newUser)
    client.clear()
  })

  it('rejects a body completing after the account boundary', async () => {
    const body = deferred<unknown>()
    vi.stubGlobal('fetch', vi.fn(async () => ({ ok: true, status: 200, json: () => body.promise })))
    const result = apiGet('/api/v1/private').catch(e => e)
    await Promise.resolve(); await Promise.resolve(); await Promise.resolve()
    await clearSession(new QueryClient())
    body.resolve({ success: true, data: 'old tenant' })
    expect(await result).toMatchObject({ name: 'AbortError' })
  })

  it('clears data when another tab changes the shared authenticated session', async () => {
    const client = new QueryClient()
    client.setQueryData(['users'], ['private'])
    useAccommodationSeasonStore().applyContext(context(1))
    const navigate = vi.fn()
    const dispose = installSessionIsolation(client, navigate)
    try {
      window.dispatchEvent(new StorageEvent('storage', { key: 'erp-hajj.sessionBoundary', newValue: 'new-session' }))
      await vi.waitFor(() => expect(navigate).toHaveBeenCalledOnce())
      expect(client.getQueryData(['users'])).toBeUndefined()
      expect(useAccommodationSeasonStore().season).toBeNull()
      expect(sessionTransition.value).toBe(true)
    } finally { dispose(); client.clear() }
  })

  it('discards a restored browser page before it can show an earlier session', async () => {
    const client = new QueryClient()
    client.setQueryData(['private'], ['old session'])
    const navigate = vi.fn()
    const dispose = installSessionIsolation(client, navigate)
    try {
      const event = new Event('pageshow')
      Object.defineProperty(event, 'persisted', { value: true })
      window.dispatchEvent(event)
      await vi.waitFor(() => expect(navigate).toHaveBeenCalledOnce())
      expect(client.getQueryData(['private'])).toBeUndefined()
      expect(sessionTransition.value).toBe(true)
    } finally { dispose(); client.clear() }
  })

  it('does not apply a late context after switching seasons, including A to B to A', () => {
    const store = useAccommodationSeasonStore()
    store.setSelectedSeasonId(1)
    const revision = store.contextGeneration
    store.setSelectedSeasonId(2)
    store.setSelectedSeasonId(1)
    store.applyContext(context(1), revision, sessionGeneration.value)
    expect(store.season).toBeNull()
    expect(store.canWrite).toBe(false)
  })
})
