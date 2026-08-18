<script setup lang="ts" generic="T">
import { computed } from 'vue'

import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import {
  useRemoteSelectOptions,
  type RemoteSelectFetcher,
} from '@/shared/composables/useRemoteSelectOptions'

const props = withDefaults(
  defineProps<{
    modelValue?: string | number | null
    queryKey: unknown
    fetcher: RemoteSelectFetcher<T>
    mapOption: (row: T) => AppSelectOption
    emptyOption?: AppSelectOption
    selectedOption?: AppSelectOption | null
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
    modelValue: null,
    selectedOption: null,
    enabled: true,
    excludeValues: () => [],
    placeholder: 'اختر…',
    searchPlaceholder: 'بحث…',
    disabled: false,
    clearable: false,
    size: 'md',
    perPage: 20,
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: string | number | null]
}>()

const remote = useRemoteSelectOptions({
  queryKey: computed(() => props.queryKey),
  fetcher: (params) => props.fetcher(params),
  mapOption: (row) => props.mapOption(row),
  enabled: computed(() => props.enabled),
  excludeValues: computed(() => props.excludeValues),
  perPage: props.perPage,
})

const options = computed(() =>
  props.emptyOption ? [props.emptyOption, ...remote.options.value] : remote.options.value,
)
</script>

<template>
  <AppSelect
    :model-value="modelValue"
    :options="options"
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
    :selected-option="selectedOption"
    @update:model-value="emit('update:modelValue', $event)"
    @search="remote.search.value = $event"
    @load-more="remote.loadMore"
    @retry="remote.retry"
  />
</template>
