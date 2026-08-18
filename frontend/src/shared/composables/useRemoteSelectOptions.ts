import { useInfiniteQuery } from '@tanstack/vue-query'
import { computed, ref, type MaybeRefOrGetter, toValue } from 'vue'

import type { AppSelectOption } from '@/shared/components/AppSelect.vue'
import { useDebouncedRef } from '@/shared/composables/useDebouncedRef'

export const REMOTE_SELECT_PER_PAGE = 20

export interface RemoteSelectMeta {
  current_page: number
  last_page: number
  per_page?: number
  total?: number
}

export interface RemoteSelectResponse<T> {
  data: T[]
  meta: RemoteSelectMeta
}

export type RemoteSelectFetcher<T> = (params: {
  search?: string
  page: number
  per_page: number
}) => Promise<RemoteSelectResponse<T>>

export function useRemoteSelectOptions<T>(options: {
  queryKey: MaybeRefOrGetter<unknown>
  fetcher: RemoteSelectFetcher<T>
  mapOption: (row: T) => AppSelectOption
  enabled?: MaybeRefOrGetter<boolean>
  perPage?: number
  excludeValues?: MaybeRefOrGetter<Array<string | number>>
}) {
  const search = ref('')
  const committedSearch = useDebouncedRef(search)

  const query = useInfiniteQuery({
    queryKey: computed(() => [
      'remote-select',
      toValue(options.queryKey),
      committedSearch.value,
    ]),
    initialPageParam: 1,
    enabled: computed(() => toValue(options.enabled) !== false),
    queryFn: ({ pageParam }): Promise<RemoteSelectResponse<T>> =>
      options.fetcher({
        search: committedSearch.value.trim() || undefined,
        page: Number(pageParam),
        per_page: options.perPage ?? REMOTE_SELECT_PER_PAGE,
      }),
    getNextPageParam: (lastPage) =>
      lastPage.meta.current_page < lastPage.meta.last_page
        ? lastPage.meta.current_page + 1
        : undefined,
  })

  const optionsList = computed(() => {
    const rows =
      query.data.value?.pages.flatMap((page) => page.data.map(options.mapOption)) ?? []
    const excluded = new Set(
      (toValue(options.excludeValues) ?? []).map((value) => String(value)),
    )
    if (excluded.size === 0) return rows
    return rows.filter((option) => !excluded.has(String(option.value)))
  })

  return {
    options: optionsList,
    search,
    committedSearch,
    isLoading: computed(
      () =>
        query.isPending.value ||
        query.isFetchingNextPage.value ||
        (query.isFetching.value && optionsList.value.length === 0),
    ),
    isError: computed(() => query.isError.value),
    hasMore: computed(() => query.hasNextPage.value === true),
    loadMore: () => {
      if (query.hasNextPage.value && !query.isFetchingNextPage.value) {
        return query.fetchNextPage()
      }
      return Promise.resolve()
    },
    retry: () => query.refetch(),
  }
}
