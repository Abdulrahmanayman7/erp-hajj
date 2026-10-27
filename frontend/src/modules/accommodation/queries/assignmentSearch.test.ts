import { createApp, computed, h, nextTick, ref } from 'vue'
import { createPinia, setActivePinia } from 'pinia'
import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query'
import { expect, it, vi } from 'vitest'
import { useAssignmentsQuery } from './useAccommodationQueries'
import { useAccommodationSeasonStore } from '../stores/seasonContextStore'

it('keeps paginated searches and season responses separate, cancelling obsolete requests', async () => {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false, gcTime: 0 } } })
  const pinia = createPinia(); setActivePinia(pinia)
  const store = useAccommodationSeasonStore(); store.setSelectedSeasonId(1)
  const pending: Array<{ resolve: (response: Response) => void; url: string; signal?: AbortSignal | null }> = []
  vi.stubGlobal('fetch', vi.fn((input: string, init?: RequestInit) => new Promise<Response>(resolve => pending.push({ resolve, url: input, signal: init?.signal }))))
  const search = ref('قديم'); const page = ref(1)
  let query!: ReturnType<typeof useAssignmentsQuery>
  const app = createApp({ setup() { query = useAssignmentsQuery(page, true, computed(() => ({ search: search.value, booking_status: 'draft' }))); return () => h('div') } })
  app.use(pinia); app.use(VueQueryPlugin, { queryClient: client }); app.mount(document.createElement('div'))
  try {
    await vi.waitFor(() => expect(pending).toHaveLength(1))
    search.value = 'جديد'; await nextTick()
    await vi.waitFor(() => expect(pending).toHaveLength(2))
    expect(pending[0]!.signal?.aborted).toBe(true)
    store.setSelectedSeasonId(2); await nextTick()
    await vi.waitFor(() => expect(pending).toHaveLength(3))
    expect(pending[1]!.signal?.aborted).toBe(true)
    const response = (id: number) => new Response(JSON.stringify({ success: true, data: [{ id }], meta: { current_page: 1, per_page: 15, total: 1, last_page: 1 } }))
    pending[2]!.resolve(response(3))
    await vi.waitFor(() => expect(query.data.value?.data[0]?.id).toBe(3))
    pending[0]!.resolve(response(1)); pending[1]!.resolve(response(2))
    await nextTick(); await nextTick()
    expect(query.data.value?.data[0]?.id).toBe(3)
    expect(decodeURIComponent(pending[2]!.url)).toContain('search=جديد')
  } finally { app.unmount(); client.clear(); vi.unstubAllGlobals() }
})
