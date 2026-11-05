<script setup lang="ts">
import { computed, reactive, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import { FilePlus2, Plus, Search } from 'lucide-vue-next'

import AppMobileFilters from '@/shared/components/AppMobileFilters.vue'
import AppPageHeader from '@/shared/components/AppPageHeader.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { useDebouncedRef } from '@/shared/composables/useDebouncedRef'

import { usePurchaseRequestsQuery } from '../queries/usePurchaseRequestsQuery'
import type { PurchaseRequestKind, PurchaseRequestStatus } from '../types/purchaseRequests'
import {
  purchaseRequestStatusBadgeClass,
  purchaseRequestStatusDotClass,
  resolvePurchaseListState,
  statusOptions,
} from '../validation/purchaseRequestValidation'

const { t } = useI18n()

const filters = reactive({
  status: '' as PurchaseRequestStatus | '',
  request_kind: '' as PurchaseRequestKind | '',
  q: '',
  page: 1,
  per_page: 20,
})

const committedSearch = useDebouncedRef(() => filters.q)

const queryParams = computed(() => ({
  status: filters.status || undefined,
  request_kind: filters.request_kind || undefined,
  q: committedSearch.value || undefined,
  page: filters.page,
  per_page: filters.per_page,
}))

const { data, isLoading, isError, refetch, isFetching } = usePurchaseRequestsQuery(queryParams)
const rows = computed(() => data.value?.data ?? [])
const meta = computed(() => data.value?.meta ?? {})
const listState = computed(() =>
  resolvePurchaseListState({
    isLoading: isLoading.value,
    isError: isError.value,
    count: rows.value.length,
  }),
)

const statusFilterOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('purchaseRequests.filters.allStatuses') },
  ...statusOptions.map((opt) => ({ value: opt.value, label: t(opt.labelKey) })),
])

const kindFilterOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('purchaseRequests.filters.allKinds') },
  { value: 'administrative', label: t('purchaseRequests.kinds.administrative') },
  { value: 'seasonal', label: t('purchaseRequests.kinds.seasonal') },
])

const activeFilterCount = computed(() => {
  let count = 0
  if (filters.status) count += 1
  if (filters.request_kind) count += 1
  return count
})

function resetFilters(): void {
  filters.status = ''
  filters.request_kind = ''
}

watch(
  () => [committedSearch.value, filters.status, filters.request_kind],
  () => {
    filters.page = 1
  },
)

function statusLabel(status: string): string {
  if (status === 'under_review') return t('purchaseRequests.statuses.underReview')
  return t(`purchaseRequests.statuses.${status}`)
}
</script>

<template>
  <div class="space-y-6">
    <AppPageHeader :title="t('purchaseRequests.title')" :subtitle="t('purchaseRequests.subtitle')">
      <template #actions>
        <PermissionGuard permission="purchase_requests.create">
          <RouterLink
            to="/app/purchase-requests/create"
            class="inline-flex items-center gap-2 rounded-xl bg-brand-primary px-3 py-2 text-sm font-semibold text-white"
          >
            <Plus class="h-4 w-4" />
            {{ t('purchaseRequests.create') }}
          </RouterLink>
        </PermissionGuard>
      </template>
    </AppPageHeader>

    <AppMobileFilters
      v-model:search="filters.q"
      :search-placeholder="t('purchaseRequests.searchPlaceholder')"
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
              :placeholder="t('purchaseRequests.searchPlaceholder')"
            />
          </div>
          <AppSelect v-model="filters.status" :options="statusFilterOptions" />
          <AppSelect v-model="filters.request_kind" :options="kindFilterOptions" />
        </div>
      </template>
      <template #filters>
        <div class="space-y-3">
          <AppSelect v-model="filters.status" :options="statusFilterOptions" />
          <AppSelect v-model="filters.request_kind" :options="kindFilterOptions" />
        </div>
      </template>
    </AppMobileFilters>

    <div class="rounded-2xl border border-brand-border bg-brand-surface">
      <div v-if="listState === 'loading'" class="p-10 text-center text-sm text-brand-text-muted">
        {{ t('purchaseRequests.loading') }}
      </div>
      <div v-else-if="listState === 'error'" class="p-10 text-center">
        <p class="text-sm text-red-700">{{ t('purchaseRequests.loadError') }}</p>
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
        {{ t('purchaseRequests.empty') }}
      </div>
      <div v-else class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="border-b border-brand-border bg-brand-bg/50 text-start text-brand-text-secondary">
            <tr>
              <th class="px-4 py-3 font-semibold">{{ t('purchaseRequests.columns.number') }}</th>
              <th class="px-4 py-3 font-semibold">{{ t('purchaseRequests.columns.title') }}</th>
              <th class="px-4 py-3 font-semibold">{{ t('purchaseRequests.columns.status') }}</th>
              <th class="px-4 py-3 font-semibold">{{ t('purchaseRequests.columns.priority') }}</th>
              <th class="px-4 py-3 font-semibold">{{ t('purchaseRequests.columns.total') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="row in rows"
              :key="row.id"
              class="border-b border-brand-border/60 last:border-0"
            >
              <td class="px-4 py-3">
                <RouterLink
                  :to="`/app/purchase-requests/${row.id}`"
                  class="font-semibold text-brand-primary hover:underline"
                >
                  {{ row.pr_number }}
                </RouterLink>
              </td>
              <td class="px-4 py-3 text-brand-text">{{ row.title }}</td>
              <td class="px-4 py-3">
                <span
                  class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold"
                  :class="purchaseRequestStatusBadgeClass(row.status)"
                >
                  <span class="h-1.5 w-1.5 rounded-full" :class="purchaseRequestStatusDotClass(row.status)" />
                  {{ statusLabel(row.status) }}
                </span>
                <span
                  v-if="row.pending_approval_engine"
                  class="ms-2 text-xs text-amber-700"
                >
                  {{ t('purchaseRequests.pendingApprovalEngine') }}
                </span>
              </td>
              <td class="px-4 py-3">{{ t(`purchaseRequests.priorities.${row.priority}`) }}</td>
              <td class="px-4 py-3">{{ row.estimated_total_priced }} {{ row.currency }}</td>
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
