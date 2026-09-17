<script setup lang="ts">
import { computed, reactive, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import { ChevronLeft, ChevronRight, Search } from 'lucide-vue-next'

import { listWarehouses } from '../api/warehousesApi'
import AppMobileFilters from '@/shared/components/AppMobileFilters.vue'
import AppPageHeader from '@/shared/components/AppPageHeader.vue'
import AppRemoteSelect from '@/shared/components/AppRemoteSelect.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import AppDateInput from '@/shared/components/AppDateInput.vue'
import { toSelectId, warehouseSelectOption } from '@/shared/lookups/selectOptions'

import { useDebouncedRef } from '@/shared/composables/useDebouncedRef'

import { useInventoryMovementsQuery } from '../queries/useMovementsQuery'
import InventoryQuantityBeforeAfter from '../components/InventoryQuantityBeforeAfter.vue'
import type { ListInventoryMovementsParams, MovementType } from '../types/movements'
import { MOVEMENT_TYPES } from '../types/movements'
import {
  formatSignedQuantity,
  movementTypeBadgeClass,
  resolveInventoryListState,
} from '../validation/inventoryValidation'

const { t } = useI18n()

const filters = reactive({
  search: '',
  warehouse_id: '' as number | '',
  type: '' as MovementType | '',
  occurred_from: '',
  occurred_to: '',
  page: 1,
  per_page: 15,
})

const committedSearch = useDebouncedRef(() => filters.search)
const params = computed<ListInventoryMovementsParams>(() => ({
  search: committedSearch.value || undefined,
  warehouse_id: filters.warehouse_id,
  type: filters.type,
  occurred_from: filters.occurred_from || undefined,
  occurred_to: filters.occurred_to || undefined,
  page: filters.page,
  per_page: filters.per_page,
}))

const { data, isLoading, isError, refetch } = useInventoryMovementsQuery(params)
const movements = computed(() => data.value?.data ?? [])
const meta = computed(() => data.value?.meta)
const listState = computed(() =>
  resolveInventoryListState({
    isLoading: isLoading.value,
    isError: isError.value,
    count: movements.value.length,
  }),
)

const fetchWarehouses = (params: { search?: string; page: number; per_page: number }) =>
  listWarehouses(params)
const emptyWarehouse = computed<AppSelectOption>(() => ({
  value: '',
  label: t('inventory.filters.allWarehouses'),
}))
const typeOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('inventory.filters.allTypes') },
  ...MOVEMENT_TYPES.map((type) => ({ value: type, label: t(`inventory.movementType.${type}`) })),
])

const activeFilterCount = computed(() => {
  let count = 0
  if (filters.warehouse_id !== '') count += 1
  if (filters.type !== '') count += 1
  if (filters.occurred_from) count += 1
  if (filters.occurred_to) count += 1
  return count
})

function resetFilters(): void {
  filters.warehouse_id = ''
  filters.type = ''
  filters.occurred_from = ''
  filters.occurred_to = ''
  filters.search = ''
}

watch(
  () => [committedSearch.value, filters.warehouse_id, filters.type, filters.occurred_from, filters.occurred_to],
  () => {
    filters.page = 1
  },
)

function formatDateTime(value: string | null): string {
  if (!value) return '—'
  try {
    return new Date(value).toLocaleString('ar-SA')
  } catch {
    return value
  }
}
</script>

<template>
  <div class="space-y-6">
    <AppPageHeader
      :title="t('inventory.movements.title')"
      :subtitle="t('inventory.movements.subtitle')"
      :meta="meta ? String(meta.total) : undefined"
    />
    <p class="inline-flex rounded-lg bg-brand-bg px-2.5 py-1 text-xs font-medium text-brand-text-muted">
      {{ t('inventory.movements.immutableHint') }}
    </p>

    <AppMobileFilters
      v-model:search="filters.search"
      :search-placeholder="t('inventory.movements.searchPlaceholder')"
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
              v-model="filters.search"
              type="search"
              class="h-11 w-full rounded-xl border border-brand-border bg-brand-surface pe-3 ps-10 text-sm text-brand-text outline-none transition placeholder:text-brand-text-muted focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15"
              :placeholder="t('inventory.movements.searchPlaceholder')"
            />
          </div>
          <AppRemoteSelect
            :model-value="filters.warehouse_id"
            query-key="warehouses"
            :fetcher="fetchWarehouses"
            :map-option="warehouseSelectOption"
            :empty-option="emptyWarehouse"
            @update:model-value="filters.warehouse_id = toSelectId($event)"
          />
          <AppSelect v-model="filters.type" :options="typeOptions" />
          <AppDateInput v-model="filters.occurred_from" :placeholder="t('inventory.columns.occurredAt')" />
          <AppDateInput v-model="filters.occurred_to" :placeholder="t('inventory.columns.occurredAt')" />
        </div>
      </template>
      <template #filters>
        <div class="space-y-3">
          <AppRemoteSelect
            :model-value="filters.warehouse_id"
            query-key="warehouses"
            :fetcher="fetchWarehouses"
            :map-option="warehouseSelectOption"
            :empty-option="emptyWarehouse"
            @update:model-value="filters.warehouse_id = toSelectId($event)"
          />
          <AppSelect v-model="filters.type" :options="typeOptions" />
          <AppDateInput v-model="filters.occurred_from" :placeholder="t('inventory.columns.occurredAt')" />
          <AppDateInput v-model="filters.occurred_to" :placeholder="t('inventory.columns.occurredAt')" />
        </div>
      </template>
    </AppMobileFilters>

    <div
      v-if="listState === 'loading'"
      class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center text-sm text-brand-text-muted"
    >
      {{ t('inventory.loading') }}
    </div>
    <div
      v-else-if="listState === 'error'"
      class="rounded-2xl border border-red-200 bg-red-50 p-10 text-center"
    >
      <p class="text-sm text-red-700">{{ t('inventory.errors.load') }}</p>
      <button
        type="button"
        class="mt-3 text-sm font-semibold text-brand-primary-dark underline"
        @click="() => refetch()"
      >
        {{ t('inventory.retry') }}
      </button>
    </div>
    <div
      v-else-if="listState === 'empty'"
      class="rounded-2xl border border-dashed border-brand-border bg-brand-surface p-10 text-center text-sm text-brand-text-muted"
    >
      {{ t('inventory.movements.empty') }}
    </div>
    <template v-else>
      <div
        class="hidden overflow-hidden rounded-2xl border border-brand-border bg-brand-surface shadow-[0_1px_2px_rgba(23,32,29,0.03)] lg:block"
      >
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead>
              <tr class="bg-[#F4F6F5]">
                <th class="px-4 py-3.5 text-start text-xs font-bold text-brand-text-muted">
                  {{ t('inventory.columns.number') }}
                </th>
                <th class="px-4 py-3.5 text-start text-xs font-bold text-brand-text-muted">
                  {{ t('inventory.columns.type') }}
                </th>
                <th class="px-4 py-3.5 text-start text-xs font-bold text-brand-text-muted">
                  {{ t('inventory.columns.item') }}
                </th>
                <th class="px-4 py-3.5 text-start text-xs font-bold text-brand-text-muted">
                  {{ t('inventory.columns.warehouse') }}
                </th>
                <th class="px-4 py-3.5 text-start text-xs font-bold text-brand-text-muted">
                  {{ t('inventory.columns.quantity') }}
                </th>
                <th class="px-4 py-3.5 text-start text-xs font-bold text-brand-text-muted">
                  {{ t('inventory.columns.beforeAfter') }}
                </th>
                <th class="px-4 py-3.5 text-start text-xs font-bold text-brand-text-muted">
                  {{ t('inventory.columns.reason') }}
                </th>
                <th class="px-4 py-3.5 text-start text-xs font-bold text-brand-text-muted">
                  {{ t('inventory.columns.reference') }}
                </th>
                <th class="px-4 py-3.5 text-start text-xs font-bold text-brand-text-muted">
                  {{ t('inventory.columns.performer') }}
                </th>
                <th class="px-4 py-3.5 text-start text-xs font-bold text-brand-text-muted">
                  {{ t('inventory.columns.occurredAt') }}
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="movement in movements"
                :key="movement.id"
                class="border-t border-brand-border align-top transition-colors hover:bg-brand-primary-dark/[0.025]"
              >
                <td class="px-4 py-3">
                  <div class="font-mono text-xs">{{ movement.movement_number }}</div>
                  <div
                    v-if="movement.transfer_group_id"
                    class="mt-1 text-[11px] text-brand-text-muted"
                  >
                    {{ t('inventory.movements.transferGroup') }}: {{ movement.transfer_group_id }}
                  </div>
                </td>
                <td class="px-4 py-3">
                  <span
                    class="rounded-full px-2 py-0.5 text-xs font-semibold"
                    :class="movementTypeBadgeClass(movement.type)"
                  >
                    {{ t(`inventory.movementType.${movement.type}`) }}
                  </span>
                </td>
                <td class="px-4 py-3">
                  <RouterLink
                    v-if="movement.item"
                    :to="`/app/inventory/items/${movement.item.id}`"
                    class="hover:underline"
                  >
                    {{ movement.item.name }}
                  </RouterLink>
                </td>
                <td class="px-4 py-3">
                  <RouterLink
                    v-if="movement.warehouse"
                    :to="`/app/warehouses/${movement.warehouse.id}`"
                    class="hover:underline"
                  >
                    {{ movement.warehouse.name }}
                  </RouterLink>
                </td>
                <td
                  class="px-4 py-3 font-semibold"
                  :class="movement.direction === 'out' ? 'text-amber-800' : 'text-emerald-800'"
                >
                  {{ formatSignedQuantity(movement.quantity, movement.direction) }}
                </td>
                <td class="px-4 py-3">
                  <InventoryQuantityBeforeAfter
                    compact
                    :before="movement.balance_before"
                    :after="movement.balance_after"
                  />
                </td>
                <td class="max-w-[12rem] truncate px-4 py-3">{{ movement.reason || '—' }}</td>
                <td class="px-4 py-3">{{ movement.reference || '—' }}</td>
                <td class="px-4 py-3">{{ movement.performer?.name || '—' }}</td>
                <td class="whitespace-nowrap px-4 py-3">{{ formatDateTime(movement.occurred_at) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="space-y-3 lg:hidden">
        <article
          v-for="movement in movements"
          :key="movement.id"
          class="rounded-2xl border border-brand-border bg-brand-surface p-4 shadow-[0_1px_2px_rgba(23,32,29,0.03)]"
        >
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <p class="font-mono text-xs font-bold text-brand-primary-dark" dir="ltr">
                {{ movement.movement_number }}
              </p>
              <h3 class="mt-1 truncate font-bold text-brand-text">
                {{ movement.item?.name || '—' }}
              </h3>
            </div>
            <span
              class="inline-flex shrink-0 rounded-full px-2.5 py-1 text-[11px] font-bold"
              :class="movementTypeBadgeClass(movement.type)"
            >
              {{ t(`inventory.movementType.${movement.type}`) }}
            </span>
          </div>
          <dl class="mt-3 grid grid-cols-2 gap-2 text-xs text-brand-text-secondary">
            <div>
              <dt>{{ t('inventory.columns.quantity') }}</dt>
              <dd
                class="mt-0.5 font-semibold"
                :class="movement.direction === 'out' ? 'text-amber-800' : 'text-emerald-800'"
              >
                {{ formatSignedQuantity(movement.quantity, movement.direction) }}
              </dd>
            </div>
            <div>
              <dt>{{ t('inventory.columns.warehouse') }}</dt>
              <dd class="mt-0.5 font-medium text-brand-text">
                {{ movement.warehouse?.name || '—' }}
              </dd>
            </div>
            <div class="col-span-2">
              <dt class="sr-only">{{ t('inventory.columns.beforeAfter') }}</dt>
              <dd>
                <InventoryQuantityBeforeAfter
                  :before="movement.balance_before"
                  :after="movement.balance_after"
                />
              </dd>
            </div>
            <div class="col-span-2">
              <dt>{{ t('inventory.columns.occurredAt') }}</dt>
              <dd class="mt-0.5 font-medium text-brand-text">
                {{ formatDateTime(movement.occurred_at) }}
              </dd>
            </div>
          </dl>
        </article>
      </div>

      <div
        v-if="meta && meta.last_page > 1"
        class="flex items-center justify-between gap-3"
      >
        <button
          type="button"
          class="inline-flex h-11 items-center gap-1 rounded-xl border border-brand-border bg-brand-surface px-3 text-sm font-semibold text-brand-text transition hover:bg-brand-bg disabled:cursor-not-allowed disabled:opacity-40"
          :disabled="filters.page <= 1"
          @click="filters.page -= 1"
        >
          <ChevronRight class="h-4 w-4" :stroke-width="2" />
          <span>{{ t('inventory.prev') }}</span>
        </button>
        <span class="text-xs font-semibold text-brand-text-muted">
          {{ filters.page }} / {{ meta.last_page }}
        </span>
        <button
          type="button"
          class="inline-flex h-11 items-center gap-1 rounded-xl border border-brand-border bg-brand-surface px-3 text-sm font-semibold text-brand-text transition hover:bg-brand-bg disabled:cursor-not-allowed disabled:opacity-40"
          :disabled="filters.page >= meta.last_page"
          @click="filters.page += 1"
        >
          <span>{{ t('inventory.next') }}</span>
          <ChevronLeft class="h-4 w-4" :stroke-width="2" />
        </button>
      </div>
    </template>
  </div>
</template>
