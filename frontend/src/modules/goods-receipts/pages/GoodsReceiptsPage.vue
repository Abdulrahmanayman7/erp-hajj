<script setup lang="ts">
import { computed, reactive, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import { FilePlus2, Search } from 'lucide-vue-next'

import AppMobileFilters from '@/shared/components/AppMobileFilters.vue'
import AppPageHeader from '@/shared/components/AppPageHeader.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import { useDebouncedRef } from '@/shared/composables/useDebouncedRef'
import {
  resolvePurchaseListState,
  softStatusBadgeClass,
  softStatusDotClass,
} from '@/modules/purchase-requests/validation/purchaseRequestValidation'

import { useGoodsReceiptsQuery } from '../queries/useGoodsReceiptQuery'

const { t } = useI18n()

const filters = reactive({
  status: '',
  q: '',
  page: 1,
})

const committedSearch = useDebouncedRef(() => filters.q)
const queryParams = computed(() => ({
  status: filters.status || undefined,
  q: committedSearch.value || undefined,
  page: filters.page,
}))

watch(
  () => [committedSearch.value, filters.status],
  () => {
    filters.page = 1
  },
)

const { data, isLoading, isError, refetch, isFetching } = useGoodsReceiptsQuery(queryParams)
const rows = computed(() => data.value?.data ?? [])
const meta = computed(() => (data.value as { meta?: Record<string, number> } | undefined)?.meta ?? {})
const listState = computed(() =>
  resolvePurchaseListState({
    isLoading: isLoading.value,
    isError: isError.value,
    count: rows.value.length,
  }),
)

const statusFilterOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('goodsReceipts.filters.allStatuses') },
  { value: 'draft', label: t('goodsReceipts.statuses.draft') },
  { value: 'posted', label: t('goodsReceipts.statuses.posted') },
])

const activeFilterCount = computed(() => (filters.status ? 1 : 0))

function resetFilters(): void {
  filters.status = ''
}
</script>

<template>
  <div class="space-y-6">
    <AppPageHeader :title="t('goodsReceipts.title')" :subtitle="t('goodsReceipts.subtitle')" />

    <AppMobileFilters
      v-model:search="filters.q"
      :search-placeholder="t('goodsReceipts.searchPlaceholder')"
      :active-count="activeFilterCount"
      @reset="resetFilters"
    >
      <template #desktop>
        <div
          class="flex flex-wrap items-center gap-3 rounded-2xl border border-brand-border bg-brand-surface p-4 shadow-[0_1px_2px_rgba(23,32,29,0.03)]"
        >
          <div class="relative min-w-48 flex-1">
            <Search
              class="pointer-events-none absolute inset-s-3 top-1/2 h-4 w-4 -translate-y-1/2 text-brand-text-muted"
              :stroke-width="1.75"
              aria-hidden="true"
            />
            <input
              v-model="filters.q"
              type="search"
              class="h-11 w-full rounded-xl border border-brand-border bg-brand-surface pe-3 ps-10 text-sm text-brand-text outline-none transition placeholder:text-brand-text-muted focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15"
              :placeholder="t('goodsReceipts.searchPlaceholder')"
            />
          </div>
          <AppSelect v-model="filters.status" :options="statusFilterOptions" />
        </div>
      </template>
      <template #filters>
        <div class="space-y-3">
          <AppSelect v-model="filters.status" :options="statusFilterOptions" />
        </div>
      </template>
    </AppMobileFilters>

    <div class="rounded-2xl border border-brand-border bg-brand-surface">
      <div v-if="listState === 'loading'" class="p-10 text-center text-sm text-brand-text-muted">
        {{ t('goodsReceipts.loading') }}
      </div>
      <div v-else-if="listState === 'error'" class="p-10 text-center">
        <p class="text-sm text-red-700">{{ t('goodsReceipts.loadError') }}</p>
        <button
          type="button"
          class="mt-3 text-sm font-semibold text-brand-primary underline"
          @click="() => { void refetch() }"
        >
          {{ t('common.retry') }}
        </button>
      </div>
      <div v-else-if="listState === 'empty'" class="p-10 text-center text-sm text-brand-text-muted">
        <FilePlus2 class="mx-auto mb-3 h-8 w-8 opacity-50" />
        {{ t('goodsReceipts.empty') }}
      </div>
      <div v-else class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="border-b border-brand-border bg-brand-bg/50 text-start text-brand-text-secondary">
            <tr>
              <th class="px-4 py-3 font-semibold">{{ t('goodsReceipts.columns.number') }}</th>
              <th class="px-4 py-3 font-semibold">{{ t('goodsReceipts.columns.status') }}</th>
              <th class="px-4 py-3 font-semibold">{{ t('goodsReceipts.columns.po') }}</th>
              <th class="px-4 py-3 font-semibold">{{ t('goodsReceipts.columns.warehouse') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="row in rows"
              :key="row.id"
              class="border-b border-brand-border/60 last:border-0 hover:bg-brand-bg/40"
            >
              <td class="px-4 py-3">
                <RouterLink
                  class="font-semibold text-brand-primary hover:underline"
                  :to="`/app/goods-receipts/${row.id}`"
                >
                  {{ row.grn_number }}
                </RouterLink>
              </td>
              <td class="px-4 py-3">
                <span
                  class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold"
                  :class="softStatusBadgeClass(row.status)"
                >
                  <span class="h-1.5 w-1.5 rounded-full" :class="softStatusDotClass(row.status)" />
                  {{ t(`goodsReceipts.statuses.${row.status}`) }}
                </span>
              </td>
              <td class="px-4 py-3">
                <RouterLink
                  class="text-brand-primary hover:underline"
                  :to="`/app/purchase-orders/${row.purchase_order_id}`"
                >
                  {{ row.purchase_order?.po_number ?? `#${row.purchase_order_id}` }}
                </RouterLink>
              </td>
              <td class="px-4 py-3">{{ row.warehouse?.name ?? '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <footer
        v-if="meta.last_page && meta.last_page > 1"
        class="flex items-center justify-between border-t border-brand-border px-4 py-3 text-sm"
      >
        <span class="text-brand-text-muted">
          {{ t('common.pageOf', { page: meta.current_page ?? filters.page, total: meta.last_page }) }}
        </span>
        <div class="flex gap-2">
          <button
            type="button"
            class="rounded-lg border border-brand-border px-3 py-1 disabled:opacity-50"
            :disabled="filters.page <= 1 || isFetching"
            @click="filters.page -= 1"
          >
            {{ t('common.prev') }}
          </button>
          <button
            type="button"
            class="rounded-lg border border-brand-border px-3 py-1 disabled:opacity-50"
            :disabled="filters.page >= meta.last_page || isFetching"
            @click="filters.page += 1"
          >
            {{ t('common.next') }}
          </button>
        </div>
      </footer>
    </div>
  </div>
</template>
