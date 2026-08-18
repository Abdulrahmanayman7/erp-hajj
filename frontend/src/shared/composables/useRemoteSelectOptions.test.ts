import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query'
import { createApp, defineComponent, h, nextTick } from 'vue'
import { beforeEach, afterEach, describe, expect, it, vi } from 'vitest'

import { useRemoteSelectOptions } from './useRemoteSelectOptions'

interface Row {
  id: number
  name: string
}

function mountRemoteSelect(
  fetcher: (params: { search?: string; page: number; per_page: number }) => Promise<{
    data: Row[]
    meta: { current_page: number; last_page: number }
  }>,
  extra?: { excludeValues?: number[] },
) {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false, gcTime: 0 },
    },
  })

  const state: {
    api?: ReturnType<typeof useRemoteSelectOptions<Row>>
  } = {}

  const app = createApp(
    defineComponent({
      setup() {
        state.api = useRemoteSelectOptions<Row>({
          queryKey: 'test-employees',
          fetcher,
          mapOption: (row) => ({ value: row.id, label: row.name }),
          excludeValues: extra?.excludeValues,
        })
        return () => h('div')
      },
    }),
  )

  app.use(VueQueryPlugin, { queryClient })
  app.mount(document.createElement('div'))

  return {
    api: state.api!,
    unmount: () => {
      app.unmount()
      queryClient.clear()
    },
  }
}

function page(names: string[], current: number, last: number) {
  return {
    data: names.map((name, index) => ({ id: current * 100 + index, name })),
    meta: { current_page: current, last_page: last, per_page: 20, total: last * 20 },
  }
}

describe('useRemoteSelectOptions', () => {
  beforeEach(() => {
    vi.useFakeTimers()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('debounces search and keeps the latest request', async () => {
    const fetcher = vi.fn(async ({ search, page: pageNo }: { search?: string; page: number }) => {
      if (search === 'A') return page(['قديم'], 1, 1)
      if (search === 'أحمد') return page(['أحمد محمد'], 1, 1)
      return page(['أولى'], pageNo, 3)
    })

    const { api, unmount } = mountRemoteSelect(fetcher)
    await vi.runAllTicks()
    await fetcher.mock.results[0]?.value
    await nextTick()

    expect(fetcher).toHaveBeenCalledTimes(1)
    expect(fetcher.mock.calls[0]?.[0]).toMatchObject({ page: 1, per_page: 20 })

    api.search.value = 'A'
    api.search.value = 'أح'
    api.search.value = 'أحمد'
    expect(fetcher).toHaveBeenCalledTimes(1)

    await vi.advanceTimersByTimeAsync(299)
    expect(fetcher).toHaveBeenCalledTimes(1)

    await vi.advanceTimersByTimeAsync(1)
    await vi.runAllTicks()
    await Promise.resolve()
    await nextTick()

    const searches = fetcher.mock.calls.map((call) => call[0].search)
    expect(searches.filter(Boolean)).toEqual(['أحمد'])
    expect(api.options.value.map((option) => option.label)).toEqual(['أحمد محمد'])

    unmount()
  })

  it('exposes loading, empty, error, retry, and load-more without a silent cap', async () => {
    const fetcher = vi
      .fn()
      .mockRejectedValueOnce(new Error('network'))
      .mockResolvedValueOnce(page(['واحد'], 1, 2))
      .mockResolvedValueOnce(page(['اثنان'], 2, 2))
      .mockResolvedValueOnce({ data: [], meta: { current_page: 1, last_page: 1 } })

    const { api, unmount } = mountRemoteSelect(fetcher)
    await vi.runAllTicks()
    await vi.runAllTimersAsync()
    await fetcher.mock.results[0]?.value.catch(() => undefined)
    await nextTick()

    expect(api.isError.value).toBe(true)

    await api.retry()
    await fetcher.mock.results[1]?.value
    await nextTick()

    expect(api.options.value).toHaveLength(1)
    expect(api.hasMore.value).toBe(true)

    await api.loadMore()
    await nextTick()
    expect(api.options.value.map((option) => option.label)).toEqual(['واحد', 'اثنان'])
    expect(api.hasMore.value).toBe(false)

    api.search.value = 'لا يوجد'
    await vi.advanceTimersByTimeAsync(300)
    await fetcher.mock.results.at(-1)?.value
    await nextTick()
    expect(api.options.value).toEqual([])

    unmount()
  })

  it('passes Arabic search terms through unchanged', async () => {
    const fetcher = vi.fn(
      async (_params: { search?: string; page: number; per_page: number }) => page([], 1, 1),
    )
    const { api, unmount } = mountRemoteSelect(fetcher)
    await Promise.resolve()

    api.search.value = 'النقل'
    await vi.advanceTimersByTimeAsync(300)
    await Promise.resolve()

    expect(fetcher.mock.calls.at(-1)?.[0]?.search).toBe('النقل')
    unmount()
  })
})
