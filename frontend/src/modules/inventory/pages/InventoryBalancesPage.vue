<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import {
  ArrowLeftRight,
  ChevronLeft,
  ChevronRight,
  PackageMinus,
  PackagePlus,
  RotateCcw,
  Scale,
  Search,
} from 'lucide-vue-next'

import { ApiError } from '@/shared/api/http'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { useToast } from '@/shared/composables/useToast'

import InventoryCategoriesManagerDrawer from '../components/InventoryCategoriesManagerDrawer.vue'
import StockActionDialog from '../components/StockActionDialog.vue'
import {
  useAdjustStockMutation,
  useIssueStockMutation,
  useReceiveStockMutation,
  useReturnStockMutation,
  useTransferStockMutation,
} from '../mutations/useStockMutations'
import { useInventoryBalancesQuery } from '../queries/useInventoryBalancesQuery'
import { useInventoryCategoriesQuery } from '../queries/useCategoriesQuery'
import { useInventoryItemsQuery } from '../queries/useItemsQuery'
import { useWarehousesQuery } from '../queries/useWarehousesQuery'
import type { ListInventoryBalancesParams, StockState } from '../types/balances'
import type { StockActionFormState, StockActionKind } from '../types/stock'
import {
  emptyStockActionForm,
  formatQuantity,
  mapInventoryErrorCode,
  resolveInventoryListState,
  stockStateBadgeClass,
  validateStockActionForm,
} from '../validation/inventoryValidation'

const { t } = useI18n()
const toast = useToast()

const filters = reactive({
  search: '',
  warehouse_id: '' as number | '',
  category_id: '' as number | '',
  stock_state: '' as StockState | '',
  page: 1,
  per_page: 15,
})

const params = computed<ListInventoryBalancesParams>(() => ({
  search: filters.search || undefined,
  warehouse_id: filters.warehouse_id,
  category_id: filters.category_id,
  stock_state: filters.stock_state,
  page: filters.page,
  per_page: filters.per_page,
}))

const { data, isLoading, isError, refetch } = useInventoryBalancesQuery(params)
const balances = computed(() => data.value?.data ?? [])
const meta = computed(() => data.value?.meta)
const listState = computed(() =>
  resolveInventoryListState({
    isLoading: isLoading.value,
    isError: isError.value,
    count: balances.value.length,
  }),
)

const { data: warehousesData } = useWarehousesQuery(computed(() => ({ is_active: true, per_page: 100 })))
const { data: categoriesData } = useInventoryCategoriesQuery({ is_active: true })
const { data: itemsData } = useInventoryItemsQuery(computed(() => ({ is_active: true, per_page: 100 })))

const warehouseOptions = computed<AppSelectOption[]>(() =>
  (warehousesData.value?.data ?? []).map((w) => ({
    value: w.id,
    label: w.name,
    hint: w.warehouse_number,
  })),
)
const warehouseFilterOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('inventory.filters.allWarehouses') },
  ...warehouseOptions.value,
])
const categoryFilterOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('inventory.filters.allCategories') },
  ...(categoriesData.value ?? []).map((c) => ({ value: c.id, label: c.name })),
])
const stockStateOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('inventory.filters.allStockStates') },
  { value: 'normal', label: t('inventory.stockState.normal') },
  { value: 'low', label: t('inventory.stockState.low') },
  { value: 'out_of_stock', label: t('inventory.stockState.out_of_stock') },
])
const itemOptions = computed<AppSelectOption[]>(() =>
  (itemsData.value?.data ?? []).map((item) => ({
    value: item.id,
    label: item.name,
    hint: item.item_number,
  })),
)

watch(
  () => [filters.search, filters.warehouse_id, filters.category_id, filters.stock_state],
  () => {
    filters.page = 1
  },
)

const receiveMutation = useReceiveStockMutation()
const issueMutation = useIssueStockMutation()
const returnMutation = useReturnStockMutation()
const transferMutation = useTransferStockMutation()
const adjustMutation = useAdjustStockMutation()

const actionOpen = ref(false)
const actionKind = ref<StockActionKind | null>(null)
const formError = ref('')
const fieldErrors = reactive<Record<string, string>>({})
const form = reactive<StockActionFormState>(emptyStockActionForm())
const categoriesOpen = ref(false)

const submitting = computed(
  () =>
    receiveMutation.isPending.value ||
    issueMutation.isPending.value ||
    returnMutation.isPending.value ||
    transferMutation.isPending.value ||
    adjustMutation.isPending.value,
)

function openAction(kind: StockActionKind): void {
  actionKind.value = kind
  Object.assign(form, emptyStockActionForm())
  formError.value = ''
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  actionOpen.value = true
}

function apiMessage(error: unknown): string {
  if (!(error instanceof ApiError)) return t('inventory.errors.generic')
  const mapped = mapInventoryErrorCode(error.code)
  if (mapped !== 'generic') return t(`inventory.errors.${mapped}`)
  return error.message || t('inventory.errors.generic')
}

async function submitAction(): Promise<void> {
  if (!actionKind.value) return
  formError.value = ''
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  Object.assign(fieldErrors, validateStockActionForm(actionKind.value, form))
  if (Object.keys(fieldErrors).length) return

  const quantity = form.quantity.trim()
  const reason = form.reason.trim()
  const reference = form.reference.trim() || null

  try {
    switch (actionKind.value) {
      case 'receive':
        await receiveMutation.mutateAsync({
          warehouse_id: Number(form.warehouse_id),
          inventory_item_id: Number(form.inventory_item_id),
          quantity,
          reason,
          reference,
          as_opening: form.as_opening,
        })
        toast.success(t('inventory.toasts.stockReceived'))
        break
      case 'issue':
        await issueMutation.mutateAsync({
          warehouse_id: Number(form.warehouse_id),
          inventory_item_id: Number(form.inventory_item_id),
          quantity,
          reason,
          reference,
        })
        toast.success(t('inventory.toasts.stockIssued'))
        break
      case 'return':
        await returnMutation.mutateAsync({
          warehouse_id: Number(form.warehouse_id),
          inventory_item_id: Number(form.inventory_item_id),
          quantity,
          reason,
          reference,
        })
        toast.success(t('inventory.toasts.stockReturned'))
        break
      case 'transfer':
        await transferMutation.mutateAsync({
          source_warehouse_id: Number(form.source_warehouse_id),
          destination_warehouse_id: Number(form.destination_warehouse_id),
          inventory_item_id: Number(form.inventory_item_id),
          quantity,
          reason,
          reference,
        })
        toast.success(t('inventory.toasts.stockTransferred'))
        break
      case 'adjust':
        await adjustMutation.mutateAsync({
          warehouse_id: Number(form.warehouse_id),
          inventory_item_id: Number(form.inventory_item_id),
          direction: form.direction as 'in' | 'out',
          quantity,
          reason,
          reference,
        })
        toast.success(t('inventory.toasts.stockAdjusted'))
        break
    }
    actionOpen.value = false
  } catch (error) {
    formError.value = apiMessage(error)
  }
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <h2 class="text-[1.75rem] font-bold">{{ t('inventory.balances.title') }}</h2>
        <p class="mt-1.5 text-sm text-brand-text-secondary">{{ t('inventory.balances.subtitle') }}</p>
        <div class="mt-3 flex flex-wrap gap-2 text-sm">
          <RouterLink class="text-brand-primary-dark underline" to="/app/inventory/items">
            {{ t('inventory.nav.items') }}
          </RouterLink>
          <span class="text-brand-text-muted">·</span>
          <RouterLink class="text-brand-primary-dark underline" to="/app/inventory/movements">
            {{ t('inventory.nav.movements') }}
          </RouterLink>
          <PermissionGuard permission="inventory.manage_items">
            <span class="text-brand-text-muted">·</span>
            <button type="button" class="text-brand-primary-dark underline" @click="categoriesOpen = true">
              {{ t('inventory.nav.categories') }}
            </button>
          </PermissionGuard>
        </div>
      </div>
      <div class="flex flex-wrap gap-2">
        <PermissionGuard permission="inventory.add">
          <button type="button" class="inline-flex h-11 items-center gap-2 rounded-xl bg-brand-primary-dark px-3 text-sm font-semibold text-white" @click="openAction('receive')">
            <PackagePlus class="h-4 w-4" />
            {{ t('inventory.stock.receiveCta') }}
          </button>
        </PermissionGuard>
        <PermissionGuard permission="inventory.issue">
          <button type="button" class="inline-flex h-11 items-center gap-2 rounded-xl border px-3 text-sm font-semibold" @click="openAction('issue')">
            <PackageMinus class="h-4 w-4" />
            {{ t('inventory.stock.issueCta') }}
          </button>
        </PermissionGuard>
        <PermissionGuard permission="inventory.return">
          <button type="button" class="inline-flex h-11 items-center gap-2 rounded-xl border px-3 text-sm font-semibold" @click="openAction('return')">
            <RotateCcw class="h-4 w-4" />
            {{ t('inventory.stock.returnCta') }}
          </button>
        </PermissionGuard>
        <PermissionGuard permission="inventory.transfer">
          <button type="button" class="inline-flex h-11 items-center gap-2 rounded-xl border px-3 text-sm font-semibold" @click="openAction('transfer')">
            <ArrowLeftRight class="h-4 w-4" />
            {{ t('inventory.stock.transferCta') }}
          </button>
        </PermissionGuard>
        <PermissionGuard permission="inventory.adjust">
          <button type="button" class="inline-flex h-11 items-center gap-2 rounded-xl border border-red-200 bg-red-50 px-3 text-sm font-semibold text-red-800" @click="openAction('adjust')">
            <Scale class="h-4 w-4" />
            {{ t('inventory.stock.adjustCta') }}
          </button>
        </PermissionGuard>
      </div>
    </div>

    <div class="flex flex-wrap items-center gap-3 rounded-2xl border border-brand-border bg-brand-surface p-4">
      <div class="relative min-w-48 flex-1">
        <Search class="pointer-events-none absolute inset-s-3 top-1/2 h-4 w-4 -translate-y-1/2 text-brand-text-muted" />
        <input
          v-model="filters.search"
          type="search"
          class="h-11 w-full rounded-xl border border-brand-border pe-3 ps-10 text-sm"
          :placeholder="t('inventory.balances.searchPlaceholder')"
        />
      </div>
      <AppSelect v-model="filters.warehouse_id" :options="warehouseFilterOptions" searchable />
      <AppSelect v-model="filters.category_id" :options="categoryFilterOptions" searchable />
      <AppSelect v-model="filters.stock_state" :options="stockStateOptions" />
    </div>

    <div v-if="listState === 'loading'" class="rounded-2xl border p-10 text-center text-sm text-brand-text-muted">
      {{ t('inventory.loading') }}
    </div>
    <div v-else-if="listState === 'error'" class="rounded-2xl border border-red-200 bg-red-50 p-10 text-center">
      <p class="text-sm text-red-700">{{ t('inventory.errors.load') }}</p>
      <button type="button" class="mt-3 underline" @click="() => refetch()">{{ t('inventory.retry') }}</button>
    </div>
    <div v-else-if="listState === 'empty'" class="rounded-2xl border p-10 text-center text-sm text-brand-text-muted">
      {{ t('inventory.balances.empty') }}
    </div>
    <template v-else>
      <div class="hidden overflow-hidden rounded-2xl border bg-brand-surface md:block">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="bg-[#F4F6F5]">
              <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">{{ t('inventory.columns.item') }}</th>
              <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">{{ t('inventory.columns.warehouse') }}</th>
              <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">{{ t('inventory.columns.category') }}</th>
              <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">{{ t('inventory.columns.unit') }}</th>
              <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">{{ t('inventory.columns.onHand') }}</th>
              <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">{{ t('inventory.columns.minimumStock') }}</th>
              <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">{{ t('inventory.columns.stockState') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="balance in balances" :key="balance.id" class="border-t border-brand-border">
              <td class="px-5 py-3">
                <RouterLink
                  v-if="balance.item"
                  :to="`/app/inventory/items/${balance.item.id}`"
                  class="font-semibold text-brand-primary-dark hover:underline"
                >
                  <span class="font-mono text-xs text-brand-text-muted">{{ balance.item.item_number }}</span>
                  <span class="ms-2">{{ balance.item.name }}</span>
                </RouterLink>
              </td>
              <td class="px-5 py-3">
                <RouterLink
                  v-if="balance.warehouse"
                  :to="`/app/warehouses/${balance.warehouse.id}`"
                  class="hover:underline"
                >
                  {{ balance.warehouse.name }}
                </RouterLink>
              </td>
              <td class="px-5 py-3">{{ balance.item?.category?.name || '—' }}</td>
              <td class="px-5 py-3">{{ balance.item?.unit ? t(`inventory.units.${balance.item.unit}`) : '—' }}</td>
              <td class="px-5 py-3 font-semibold">{{ formatQuantity(balance.on_hand) }}</td>
              <td class="px-5 py-3">{{ formatQuantity(balance.item?.minimum_stock) }}</td>
              <td class="px-5 py-3">
                <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="stockStateBadgeClass(balance.stock_state)">
                  {{ t(`inventory.stockState.${balance.stock_state}`) }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="space-y-3 md:hidden">
        <article v-for="balance in balances" :key="balance.id" class="rounded-2xl border bg-brand-surface p-4">
          <div class="flex items-start justify-between gap-2">
            <div>
              <p class="font-mono text-xs text-brand-text-muted">{{ balance.item?.item_number }}</p>
              <h3 class="font-bold">{{ balance.item?.name }}</h3>
              <p class="mt-1 text-sm text-brand-text-secondary">{{ balance.warehouse?.name }}</p>
            </div>
            <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="stockStateBadgeClass(balance.stock_state)">
              {{ t(`inventory.stockState.${balance.stock_state}`) }}
            </span>
          </div>
          <p class="mt-3 text-sm">
            {{ t('inventory.columns.onHand') }}:
            <strong>{{ formatQuantity(balance.on_hand) }}</strong>
            {{ balance.item?.unit ? t(`inventory.units.${balance.item.unit}`) : '' }}
          </p>
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

    <StockActionDialog
      :open="actionOpen"
      :kind="actionKind"
      :form="form"
      :form-error="formError"
      :field-errors="fieldErrors"
      :submitting="submitting"
      :warehouse-options="warehouseOptions"
      :item-options="itemOptions"
      @close="actionOpen = false"
      @submit="submitAction"
      @update:form="Object.assign(form, $event)"
    />

    <InventoryCategoriesManagerDrawer :open="categoriesOpen" @close="categoriesOpen = false" />
  </div>
</template>
