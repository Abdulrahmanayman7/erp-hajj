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

import { listWarehouses } from '../api/warehousesApi'
import { ApiError } from '@/shared/api/http'
import AppMobileFilters from '@/shared/components/AppMobileFilters.vue'
import AppPageHeader from '@/shared/components/AppPageHeader.vue'
import AppRemoteSelect from '@/shared/components/AppRemoteSelect.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import { toSelectId, warehouseSelectOption } from '@/shared/lookups/selectOptions'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { useToast } from '@/shared/composables/useToast'
import { useDebouncedRef } from '@/shared/composables/useDebouncedRef'

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
import type { ListInventoryBalancesParams, StockState } from '../types/balances'
import type { StockActionFormState, StockActionKind } from '../types/stock'
import {
  emptyStockActionForm,
  formatQuantity,
  mapInventoryErrorCode,
  resolveInventoryListState,
  stockStateBadgeClass,
  stockStateDotClass,
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

const committedSearch = useDebouncedRef(() => filters.search)
const params = computed<ListInventoryBalancesParams>(() => ({
  search: committedSearch.value || undefined,
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

const { data: categoriesData } = useInventoryCategoriesQuery({ is_active: true })
const fetchActiveWarehouses = (params: { search?: string; page: number; per_page: number }) =>
  listWarehouses({ ...params, is_active: true })
const emptyWarehouse = computed<AppSelectOption>(() => ({
  value: '',
  label: t('inventory.filters.allWarehouses'),
}))
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

const activeFilterCount = computed(() => {
  let count = 0
  if (filters.warehouse_id !== '') count += 1
  if (filters.category_id !== '') count += 1
  if (filters.stock_state !== '') count += 1
  return count
})

function resetFilters(): void {
  filters.warehouse_id = ''
  filters.category_id = ''
  filters.stock_state = ''
  filters.search = ''
}

watch(
  () => [committedSearch.value, filters.warehouse_id, filters.category_id, filters.stock_state],
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
    <AppPageHeader
      :title="t('inventory.balances.title')"
      :subtitle="t('inventory.balances.subtitle')"
      :meta="meta ? t('inventory.balances.total', { count: meta.total }) : undefined"
    >
      <template #actions>
        <PermissionGuard permission="inventory.add">
          <button
            type="button"
            class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-3.5 text-sm font-semibold text-emerald-950 shadow-sm transition hover:bg-emerald-100 focus:outline-none focus:ring-2 focus:ring-emerald-200/60 sm:w-auto"
            @click="openAction('receive')"
          >
            <PackagePlus class="h-4 w-4" :stroke-width="2" />
            <span>{{ t('inventory.stock.receiveCta') }}</span>
          </button>
        </PermissionGuard>
        <PermissionGuard permission="inventory.issue">
          <button
            type="button"
            class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl border border-amber-200 bg-amber-50 px-3.5 text-sm font-semibold text-amber-950 shadow-sm transition hover:bg-amber-100 focus:outline-none focus:ring-2 focus:ring-amber-200/60 sm:w-auto"
            @click="openAction('issue')"
          >
            <PackageMinus class="h-4 w-4" :stroke-width="2" />
            <span>{{ t('inventory.stock.issueCta') }}</span>
          </button>
        </PermissionGuard>
        <PermissionGuard permission="inventory.return">
          <button
            type="button"
            class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl border border-sky-200 bg-sky-50 px-3.5 text-sm font-semibold text-sky-950 shadow-sm transition hover:bg-sky-100 focus:outline-none focus:ring-2 focus:ring-sky-200/60 sm:w-auto"
            @click="openAction('return')"
          >
            <RotateCcw class="h-4 w-4" :stroke-width="2" />
            <span>{{ t('inventory.stock.returnCta') }}</span>
          </button>
        </PermissionGuard>
        <PermissionGuard permission="inventory.transfer">
          <button
            type="button"
            class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl border border-teal-200 bg-teal-50 px-3.5 text-sm font-semibold text-teal-950 shadow-sm transition hover:bg-teal-100 focus:outline-none focus:ring-2 focus:ring-teal-200/60 sm:w-auto"
            @click="openAction('transfer')"
          >
            <ArrowLeftRight class="h-4 w-4" :stroke-width="2" />
            <span>{{ t('inventory.stock.transferCta') }}</span>
          </button>
        </PermissionGuard>
        <PermissionGuard permission="inventory.adjust">
          <button
            type="button"
            class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl border border-red-200 bg-red-50 px-3.5 text-sm font-semibold text-red-800 shadow-sm transition hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-red-200/60 sm:w-auto"
            @click="openAction('adjust')"
          >
            <Scale class="h-4 w-4" :stroke-width="2" />
            <span>{{ t('inventory.stock.adjustCta') }}</span>
          </button>
        </PermissionGuard>
      </template>
    </AppPageHeader>

    <div class="flex flex-wrap gap-2">
      <RouterLink
        to="/app/inventory/items"
        class="inline-flex h-9 items-center rounded-xl border border-brand-border bg-brand-bg px-3 text-sm font-semibold text-brand-text transition hover:border-brand-primary/30 hover:bg-brand-primary-soft hover:text-brand-primary-dark"
      >
        {{ t('inventory.nav.items') }}
      </RouterLink>
      <RouterLink
        to="/app/inventory/movements"
        class="inline-flex h-9 items-center rounded-xl border border-brand-border bg-brand-bg px-3 text-sm font-semibold text-brand-text transition hover:border-brand-primary/30 hover:bg-brand-primary-soft hover:text-brand-primary-dark"
      >
        {{ t('inventory.nav.movements') }}
      </RouterLink>
      <PermissionGuard permission="inventory.manage_items">
        <button
          type="button"
          class="inline-flex h-9 items-center rounded-xl border border-brand-border bg-brand-bg px-3 text-sm font-semibold text-brand-text transition hover:border-brand-primary/30 hover:bg-brand-primary-soft hover:text-brand-primary-dark"
          @click="categoriesOpen = true"
        >
          {{ t('inventory.nav.categories') }}
        </button>
      </PermissionGuard>
    </div>

    <AppMobileFilters
      v-model:search="filters.search"
      :search-placeholder="t('inventory.balances.searchPlaceholder')"
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
              :placeholder="t('inventory.balances.searchPlaceholder')"
            />
          </div>
          <AppRemoteSelect
            :model-value="filters.warehouse_id"
            query-key="warehouses-active"
            :fetcher="fetchActiveWarehouses"
            :map-option="warehouseSelectOption"
            :empty-option="emptyWarehouse"
            @update:model-value="filters.warehouse_id = toSelectId($event)"
          />
          <AppSelect v-model="filters.category_id" :options="categoryFilterOptions" searchable />
          <AppSelect v-model="filters.stock_state" :options="stockStateOptions" />
        </div>
      </template>
      <template #filters>
        <div class="space-y-3">
          <AppRemoteSelect
            :model-value="filters.warehouse_id"
            query-key="warehouses-active"
            :fetcher="fetchActiveWarehouses"
            :map-option="warehouseSelectOption"
            :empty-option="emptyWarehouse"
            @update:model-value="filters.warehouse_id = toSelectId($event)"
          />
          <AppSelect v-model="filters.category_id" :options="categoryFilterOptions" searchable />
          <AppSelect v-model="filters.stock_state" :options="stockStateOptions" />
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
      {{ t('inventory.balances.empty') }}
    </div>
    <template v-else>
      <div
        class="hidden overflow-hidden rounded-2xl border border-brand-border bg-brand-surface shadow-[0_1px_2px_rgba(23,32,29,0.03)] lg:block"
      >
        <div class="overflow-x-auto">
          <table class="min-w-full border-separate border-spacing-0 text-sm">
            <thead>
              <tr class="bg-[#F4F6F5]">
                <th
                  v-for="(key, columnIndex) in [
                    'item',
                    'warehouse',
                    'category',
                    'unit',
                    'onHand',
                    'minimumStock',
                    'stockState',
                  ]"
                  :key="key"
                  class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-center text-xs font-bold tracking-wide text-brand-text"
                  :class="columnIndex === 0 ? 'border-s-[3px] border-s-transparent' : ''"
                >
                  {{ t(`inventory.columns.${key}`) }}
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(balance, index) in balances"
                :key="balance.id"
                class="group"
                :class="index % 2 === 1 ? 'bg-[#FAFBFA]' : 'bg-brand-surface'"
              >
                <td
                  class="max-w-[18rem] whitespace-nowrap border-b border-s-[3px] border-brand-border/80 border-s-transparent px-5 py-3.5 text-center transition-colors duration-150 group-hover:border-s-brand-primary group-hover:bg-[#EDF6F1]"
                >
                  <RouterLink
                    v-if="balance.item"
                    :to="`/app/inventory/items/${balance.item.id}`"
                    class="inline-flex max-w-full items-center justify-center gap-2"
                    :title="balance.item.name"
                  >
                    <span
                      class="inline-flex shrink-0 items-center rounded-lg border border-brand-border bg-brand-bg px-2.5 py-1 font-mono text-[12px] font-bold tracking-wide text-brand-text shadow-[0_1px_0_rgba(23,32,29,0.04)] transition group-hover:border-brand-primary/30 group-hover:bg-brand-surface"
                      dir="ltr"
                    >
                      {{ balance.item.item_number }}
                    </span>
                    <span
                      class="truncate font-semibold text-brand-text transition hover:text-brand-primary-dark hover:underline hover:underline-offset-2"
                    >
                      {{ balance.item.name }}
                    </span>
                  </RouterLink>
                  <span v-else class="text-brand-text">—</span>
                </td>
                <td
                  class="max-w-[12rem] whitespace-nowrap border-b border-brand-border/80 px-5 py-3.5 text-center text-brand-text transition-colors duration-150 group-hover:bg-[#EDF6F1]"
                >
                  <RouterLink
                    v-if="balance.warehouse"
                    :to="`/app/warehouses/${balance.warehouse.id}`"
                    class="block truncate font-semibold text-brand-text transition hover:text-brand-primary-dark hover:underline hover:underline-offset-2"
                    :title="balance.warehouse.name"
                  >
                    {{ balance.warehouse.name }}
                  </RouterLink>
                  <span v-else>—</span>
                </td>
                <td
                  class="max-w-[10rem] whitespace-nowrap border-b border-brand-border/80 px-5 py-3.5 text-center text-brand-text transition-colors duration-150 group-hover:bg-[#EDF6F1]"
                >
                  <span class="block truncate" :title="balance.item?.category?.name || undefined">
                    {{ balance.item?.category?.name || '—' }}
                  </span>
                </td>
                <td
                  class="whitespace-nowrap border-b border-brand-border/80 px-5 py-3.5 text-center text-brand-text transition-colors duration-150 group-hover:bg-[#EDF6F1]"
                >
                  {{ balance.item?.unit ? t(`inventory.units.${balance.item.unit}`) : '—' }}
                </td>
                <td
                  class="whitespace-nowrap border-b border-brand-border/80 px-5 py-3.5 text-center font-semibold text-brand-text transition-colors duration-150 group-hover:bg-[#EDF6F1]"
                  dir="ltr"
                >
                  {{ formatQuantity(balance.on_hand) }}
                </td>
                <td
                  class="whitespace-nowrap border-b border-brand-border/80 px-5 py-3.5 text-center text-brand-text transition-colors duration-150 group-hover:bg-[#EDF6F1]"
                  dir="ltr"
                >
                  {{ formatQuantity(balance.item?.minimum_stock) }}
                </td>
                <td
                  class="whitespace-nowrap border-b border-brand-border/80 px-5 py-3.5 text-center transition-colors duration-150 group-hover:bg-[#EDF6F1]"
                >
                  <span
                    class="inline-flex min-w-[6.5rem] items-center justify-center gap-1.5 rounded-full px-3 py-1 text-[11px] font-bold tracking-wide shadow-sm"
                    :class="stockStateBadgeClass(balance.stock_state)"
                  >
                    <span
                      class="h-1.5 w-1.5 shrink-0 rounded-full"
                      :class="stockStateDotClass(balance.stock_state)"
                      aria-hidden="true"
                    />
                    {{ t(`inventory.stockState.${balance.stock_state}`) }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="space-y-3 lg:hidden">
        <article
          v-for="balance in balances"
          :key="balance.id"
          class="rounded-2xl border border-brand-border bg-brand-surface p-4 shadow-[0_1px_2px_rgba(23,32,29,0.03)]"
        >
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <p class="font-mono text-xs font-bold text-brand-primary-dark" dir="ltr">
                {{ balance.item?.item_number || '—' }}
              </p>
              <h3 class="mt-1 truncate font-bold text-brand-text">
                <RouterLink
                  v-if="balance.item"
                  :to="`/app/inventory/items/${balance.item.id}`"
                  class="transition hover:text-brand-primary-dark hover:underline hover:underline-offset-2"
                >
                  {{ balance.item.name }}
                </RouterLink>
                <template v-else>—</template>
              </h3>
            </div>
            <span
              class="inline-flex shrink-0 items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-bold shadow-sm"
              :class="stockStateBadgeClass(balance.stock_state)"
            >
              <span
                class="h-1.5 w-1.5 rounded-full"
                :class="stockStateDotClass(balance.stock_state)"
              />
              {{ t(`inventory.stockState.${balance.stock_state}`) }}
            </span>
          </div>
          <dl class="mt-3 grid grid-cols-2 gap-2 text-xs text-brand-text-secondary">
            <div>
              <dt>{{ t('inventory.columns.warehouse') }}</dt>
              <dd class="mt-0.5 font-medium text-brand-text">
                <RouterLink
                  v-if="balance.warehouse"
                  :to="`/app/warehouses/${balance.warehouse.id}`"
                  class="transition hover:text-brand-primary-dark hover:underline hover:underline-offset-2"
                >
                  {{ balance.warehouse.name }}
                </RouterLink>
                <template v-else>—</template>
              </dd>
            </div>
            <div>
              <dt>{{ t('inventory.columns.onHand') }}</dt>
              <dd class="mt-0.5 font-medium text-brand-text" dir="ltr">
                {{ formatQuantity(balance.on_hand) }}
                {{ balance.item?.unit ? t(`inventory.units.${balance.item.unit}`) : '' }}
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

    <StockActionDialog
      :open="actionOpen"
      :kind="actionKind"
      :form="form"
      :form-error="formError"
      :field-errors="fieldErrors"
      :submitting="submitting"
      @close="actionOpen = false"
      @submit="submitAction"
      @update:form="Object.assign(form, $event)"
    />

    <InventoryCategoriesManagerDrawer :open="categoriesOpen" @close="categoriesOpen = false" />
  </div>
</template>
