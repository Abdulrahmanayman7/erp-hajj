<script setup lang="ts">
import { computed, reactive, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import { ChevronLeft, ChevronRight, Search } from 'lucide-vue-next'

import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'

import { useInventoryMovementsQuery } from '../queries/useMovementsQuery'
import { useWarehousesQuery } from '../queries/useWarehousesQuery'
import type { ListInventoryMovementsParams, MovementType } from '../types/movements'
import { MOVEMENT_TYPES } from '../types/movements'
import {
  formatQuantity,
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

const params = computed<ListInventoryMovementsParams>(() => ({
  search: filters.search || undefined,
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

const { data: warehousesData } = useWarehousesQuery(computed(() => ({ per_page: 100 })))
const warehouseOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('inventory.filters.allWarehouses') },
  ...(warehousesData.value?.data ?? []).map((w) => ({ value: w.id, label: w.name })),
])
const typeOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('inventory.filters.allTypes') },
  ...MOVEMENT_TYPES.map((type) => ({ value: type, label: t(`inventory.movementType.${type}`) })),
])

watch(
  () => [filters.search, filters.warehouse_id, filters.type, filters.occurred_from, filters.occurred_to],
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
    <div>
      <h2 class="text-[1.75rem] font-bold">{{ t('inventory.movements.title') }}</h2>
      <p class="mt-1.5 text-sm text-brand-text-secondary">{{ t('inventory.movements.subtitle') }}</p>
      <p class="mt-2 text-xs text-brand-text-muted">{{ t('inventory.movements.immutableHint') }}</p>
    </div>

    <div class="flex flex-wrap items-center gap-3 rounded-2xl border bg-brand-surface p-4">
      <div class="relative min-w-48 flex-1">
        <Search class="pointer-events-none absolute inset-s-3 top-1/2 h-4 w-4 -translate-y-1/2 text-brand-text-muted" />
        <input
          v-model="filters.search"
          type="search"
          class="h-11 w-full rounded-xl border pe-3 ps-10 text-sm"
          :placeholder="t('inventory.movements.searchPlaceholder')"
        />
      </div>
      <AppSelect v-model="filters.warehouse_id" :options="warehouseOptions" searchable />
      <AppSelect v-model="filters.type" :options="typeOptions" />
      <input v-model="filters.occurred_from" type="date" class="h-11 rounded-xl border px-3 text-sm" />
      <input v-model="filters.occurred_to" type="date" class="h-11 rounded-xl border px-3 text-sm" />
    </div>

    <div v-if="listState === 'loading'" class="rounded-2xl border p-10 text-center text-sm text-brand-text-muted">
      {{ t('inventory.loading') }}
    </div>
    <div v-else-if="listState === 'error'" class="rounded-2xl border border-red-200 bg-red-50 p-10 text-center">
      <p class="text-sm text-red-700">{{ t('inventory.errors.load') }}</p>
      <button type="button" class="mt-3 underline" @click="() => refetch()">{{ t('inventory.retry') }}</button>
    </div>
    <div v-else-if="listState === 'empty'" class="rounded-2xl border p-10 text-center text-sm text-brand-text-muted">
      {{ t('inventory.movements.empty') }}
    </div>
    <template v-else>
      <div class="hidden overflow-hidden rounded-2xl border bg-brand-surface md:block">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="bg-[#F4F6F5]">
              <th class="px-4 py-3.5 text-start text-xs font-bold text-brand-text-muted">{{ t('inventory.columns.number') }}</th>
              <th class="px-4 py-3.5 text-start text-xs font-bold text-brand-text-muted">{{ t('inventory.columns.type') }}</th>
              <th class="px-4 py-3.5 text-start text-xs font-bold text-brand-text-muted">{{ t('inventory.columns.item') }}</th>
              <th class="px-4 py-3.5 text-start text-xs font-bold text-brand-text-muted">{{ t('inventory.columns.warehouse') }}</th>
              <th class="px-4 py-3.5 text-start text-xs font-bold text-brand-text-muted">{{ t('inventory.columns.quantity') }}</th>
              <th class="px-4 py-3.5 text-start text-xs font-bold text-brand-text-muted">{{ t('inventory.columns.beforeAfter') }}</th>
              <th class="px-4 py-3.5 text-start text-xs font-bold text-brand-text-muted">{{ t('inventory.columns.reason') }}</th>
              <th class="px-4 py-3.5 text-start text-xs font-bold text-brand-text-muted">{{ t('inventory.columns.reference') }}</th>
              <th class="px-4 py-3.5 text-start text-xs font-bold text-brand-text-muted">{{ t('inventory.columns.performer') }}</th>
              <th class="px-4 py-3.5 text-start text-xs font-bold text-brand-text-muted">{{ t('inventory.columns.occurredAt') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="movement in movements" :key="movement.id" class="border-t align-top">
              <td class="px-4 py-3">
                <div class="font-mono text-xs">{{ movement.movement_number }}</div>
                <div v-if="movement.transfer_group_id" class="mt-1 text-[11px] text-brand-text-muted">
                  {{ t('inventory.movements.transferGroup') }}: {{ movement.transfer_group_id }}
                </div>
              </td>
              <td class="px-4 py-3">
                <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="movementTypeBadgeClass(movement.type)">
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
              <td class="px-4 py-3 text-xs">
                {{ formatQuantity(movement.balance_before) }} → {{ formatQuantity(movement.balance_after) }}
              </td>
              <td class="px-4 py-3 max-w-[12rem] truncate">{{ movement.reason || '—' }}</td>
              <td class="px-4 py-3">{{ movement.reference || '—' }}</td>
              <td class="px-4 py-3">{{ movement.performer?.name || '—' }}</td>
              <td class="px-4 py-3 whitespace-nowrap">{{ formatDateTime(movement.occurred_at) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="space-y-3 md:hidden">
        <article v-for="movement in movements" :key="movement.id" class="rounded-2xl border bg-brand-surface p-4">
          <div class="flex items-start justify-between gap-2">
            <div>
              <p class="font-mono text-xs">{{ movement.movement_number }}</p>
              <p class="mt-1 font-semibold">{{ movement.item?.name }}</p>
              <p class="text-sm text-brand-text-secondary">{{ movement.warehouse?.name }}</p>
            </div>
            <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="movementTypeBadgeClass(movement.type)">
              {{ t(`inventory.movementType.${movement.type}`) }}
            </span>
          </div>
          <p class="mt-2 font-semibold" :class="movement.direction === 'out' ? 'text-amber-800' : 'text-emerald-800'">
            {{ formatSignedQuantity(movement.quantity, movement.direction) }}
          </p>
          <p class="mt-1 text-xs text-brand-text-muted">{{ formatDateTime(movement.occurred_at) }}</p>
        </article>
      </div>

      <div v-if="meta && meta.last_page > 1" class="flex items-center justify-between gap-3">
        <button type="button" class="inline-flex items-center gap-1 rounded-xl border px-3 py-2 text-sm disabled:opacity-40" :disabled="filters.page <= 1" @click="filters.page -= 1">
          <ChevronRight class="h-4 w-4" />
          {{ t('inventory.prev') }}
        </button>
        <span class="text-sm text-brand-text-muted">{{ filters.page }} / {{ meta.last_page }}</span>
        <button type="button" class="inline-flex items-center gap-1 rounded-xl border px-3 py-2 text-sm disabled:opacity-40" :disabled="filters.page >= meta.last_page" @click="filters.page += 1">
          {{ t('inventory.next') }}
          <ChevronLeft class="h-4 w-4" />
        </button>
      </div>
    </template>
  </div>
</template>
