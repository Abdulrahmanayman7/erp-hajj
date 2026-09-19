<script setup lang="ts" generic="T">
import { computed } from 'vue'

import AppListSelect, { type AppListSelectOption } from '@/shared/components/AppListSelect.vue'
import {
  useRemoteSelectOptions,
  type RemoteSelectFetcher,
} from '@/shared/composables/useRemoteSelectOptions'

const props = withDefaults(
  defineProps<{
    modelValue?: Array<string | number>
    queryKey: unknown
    fetcher: RemoteSelectFetcher<T>
    mapOption: (row: T) => AppListSelectOption
    selectedOptions?: AppListSelectOption[]
    enabled?: boolean
    excludeValues?: Array<string | number>
    placeholder?: string
    searchPlaceholder?: string
    disabled?: boolean
    clearable?: boolean
    size?: 'sm' | 'md'
    perPage?: number
  }>(),
  {
    modelValue: () => [],
    selectedOptions: () => [],
    enabled: true,
    excludeValues: () => [],
    placeholder: 'اختر…',
    searchPlaceholder: 'بحث…',
    disabled: false,
    clearable: true,
    size: 'md',
    perPage: 20,
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: Array<string | number>]
}>()

const remote = useRemoteSelectOptions({
  queryKey: computed(() => props.queryKey),
  fetcher: (params) => props.fetcher(params),
  mapOption: (row) => props.mapOption(row),
  enabled: computed(() => props.enabled),
  excludeValues: computed(() => props.excludeValues),
  perPage: props.perPage,
})
</script>

<template>
  <AppListSelect
    :model-value="modelValue"
    :options="remote.options.value"
    :selected-options="selectedOptions"
    :placeholder="placeholder"
    :search-placeholder="searchPlaceholder"
    :disabled="disabled"
    :clearable="clearable"
    :size="size"
    searchable
    remote
    :loading="remote.isLoading.value"
    :error="remote.isError.value"
    :has-more="remote.hasMore.value"
    @update:model-value="emit('update:modelValue', $event)"
    @search="remote.search.value = $event"
    @load-more="remote.loadMore"
    @retry="remote.retry"
  />
</template>
