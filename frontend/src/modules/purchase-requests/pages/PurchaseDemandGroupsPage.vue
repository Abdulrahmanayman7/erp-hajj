<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import { FilePlus2, Plus, Search } from 'lucide-vue-next'

import AppMobileFilters from '@/shared/components/AppMobileFilters.vue'
import AppPageHeader from '@/shared/components/AppPageHeader.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { useDebouncedRef } from '@/shared/composables/useDebouncedRef'
import { usePermissions } from '@/shared/composables/usePermissions'
import {
  resolvePurchaseListState,
  softStatusBadgeClass,
  softStatusDotClass,
} from '../validation/purchaseRequestValidation'

import {
  createDemandGroup,
  listDemandGroups,
} from '../api/demandGroupsApi'
import type { DemandGroup, DemandGroupStatus } from '../types/demandGroups'

const { t } = useI18n()
const { can } = usePermissions()

const filters = reactive({
  status: '' as DemandGroupStatus | '',
  q: '',
  page: 1,
  per_page: 20,
})

const committedSearch = useDebouncedRef(() => filters.q)
const rows = ref<DemandGroup[]>([])
const meta = ref<Record<string, number>>({})
const isLoading = ref(false)
const isError = ref(false)
const createOpen = ref(false)
const createTitle = ref('')
const createBusy = ref(false)
const createError = ref('')

const listState = computed(() =>
  resolvePurchaseListState({
    isLoading: isLoading.value,
    isError: isError.value,
    count: rows.value.length,
  }),
)

const statusFilterOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('purchaseDemandGroups.allStatuses') },
  { value: 'draft', label: t('purchaseDemandGroups.statuses.draft') },
  { value: 'active', label: t('purchaseDemandGroups.statuses.active') },
  { value: 'frozen', label: t('purchaseDemandGroups.statuses.frozen') },
  { value: 'closed', label: t('purchaseDemandGroups.statuses.closed') },
])

const activeFilterCount = computed(() => (filters.status ? 1 : 0))
const canMutate = computed(() => can('purchase_requests.consolidate_planning'))

async function load(): Promise<void> {
  isLoading.value = true
  isError.value = false
  try {
    const result = await listDemandGroups({
      status: filters.status || undefined,
      q: committedSearch.value || undefined,
      page: filters.page,
      per_page: filters.per_page,
    })
    rows.value = result.data
    meta.value = result.meta
  } catch {
    isError.value = true
  } finally {
    isLoading.value = false
  }
}

function resetFilters(): void {
  filters.status = ''
}

watch(
  () => [committedSearch.value, filters.status, filters.page],
  () => {
    void load()
  },
  { immediate: true },
)

watch(
  () => [committedSearch.value, filters.status],
  () => {
    filters.page = 1
  },
)

async function submitCreate(): Promise<void> {
  createBusy.value = true
  createError.value = ''
  try {
    await createDemandGroup({ title: createTitle.value.trim() })
    createOpen.value = false
    createTitle.value = ''
    await load()
  } catch (e) {
    createError.value = e instanceof Error ? e.message : t('purchaseDemandGroups.createFailed')
  } finally {
    createBusy.value = false
  }
}
</script>

<template>
  <div class="space-y-6">
    <AppPageHeader :title="t('purchaseDemandGroups.title')" :subtitle="t('purchaseDemandGroups.subtitle')">
      <template #actions>
        <PermissionGuard permission="purchase_requests.consolidate_planning">
          <button
            type="button"
            class="inline-flex items-center gap-2 rounded-xl bg-brand-primary px-3 py-2 text-sm font-semibold text-white"
            @click="createOpen = true"
          >
            <Plus class="h-4 w-4" />
            {{ t('purchaseDemandGroups.create') }}
          </button>
        </PermissionGuard>
      </template>
    </AppPageHeader>

    <p class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
      {{ t('purchaseDemandGroups.noRfqsBanner') }}
    </p>

    <AppMobileFilters
      v-model:search="filters.q"
      :search-placeholder="t('purchaseDemandGroups.search')"
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
              :placeholder="t('purchaseDemandGroups.search')"
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
        {{ t('common.loading') }}
      </div>
      <div v-else-if="listState === 'error'" class="p-10 text-center">
        <p class="text-sm text-red-700">{{ t('common.error') }}</p>
        <button
          type="button"
          class="mt-3 text-sm font-semibold text-brand-primary underline"
          @click="() => { void load() }"
        >
          {{ t('common.retry') }}
        </button>
      </div>
      <div v-else-if="listState === 'empty'" class="p-10 text-center text-sm text-brand-text-muted">
        <FilePlus2 class="mx-auto mb-3 h-8 w-8 opacity-50" />
        {{ t('purchaseDemandGroups.empty') }}
      </div>
      <div v-else class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="border-b border-brand-border bg-brand-bg/50 text-start text-brand-text-secondary">
            <tr>
              <th class="px-4 py-3 font-semibold">{{ t('purchaseDemandGroups.number') }}</th>
              <th class="px-4 py-3 font-semibold">{{ t('purchaseDemandGroups.name') }}</th>
              <th class="px-4 py-3 font-semibold">{{ t('purchaseDemandGroups.status') }}</th>
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
                  class="font-semibold text-brand-primary hover:underline"
                  :to="`/app/purchase-demand-groups/${row.id}`"
                >
                  {{ row.group_number }}
                </RouterLink>
              </td>
              <td class="px-4 py-3 text-brand-text">{{ row.title }}</td>
              <td class="px-4 py-3">
                <span
                  class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold"
                  :class="softStatusBadgeClass(row.status)"
                >
                  <span class="h-1.5 w-1.5 rounded-full" :class="softStatusDotClass(row.status)" />
                  {{ t(`purchaseDemandGroups.statuses.${row.status}`) }}
                </span>
              </td>
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
            :disabled="filters.page <= 1 || isLoading"
            @click="filters.page -= 1"
          >
            {{ t('common.prev') }}
          </button>
          <button
            type="button"
            class="rounded-lg border border-brand-border px-3 py-1 disabled:opacity-50"
            :disabled="filters.page >= meta.last_page || isLoading"
            @click="filters.page += 1"
          >
            {{ t('common.next') }}
          </button>
        </div>
      </footer>
    </div>

    <div
      v-if="createOpen && canMutate"
      class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4"
      @click.self="createOpen = false"
    >
      <div class="w-full max-w-md space-y-4 rounded-2xl border border-brand-border bg-brand-surface p-5 shadow-xl">
        <h2 class="text-lg font-bold text-brand-text">{{ t('purchaseDemandGroups.create') }}</h2>
        <label class="block text-sm">
          <span class="mb-1 block text-brand-text-secondary">{{ t('purchaseDemandGroups.titleField') }}</span>
          <input
            v-model="createTitle"
            class="w-full rounded-xl border border-brand-border px-3 py-2 text-sm"
            :placeholder="t('purchaseDemandGroups.titleField')"
          />
        </label>
        <p v-if="createError" class="text-sm text-red-700">{{ createError }}</p>
        <div class="flex justify-end gap-2">
          <button
            type="button"
            class="rounded-xl px-4 py-2 text-sm text-brand-text-secondary"
            @click="createOpen = false"
          >
            {{ t('common.cancel') }}
          </button>
          <button
            type="button"
            class="rounded-xl bg-brand-primary px-4 py-2 text-sm font-bold text-white disabled:opacity-50"
            :disabled="createBusy || createTitle.trim().length < 3"
            @click="submitCreate"
          >
            {{ t('common.save') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
