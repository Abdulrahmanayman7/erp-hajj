<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import {
  ArrowRight,
  Barcode,
  FileText,
  Package,
  Pencil,
  Tag,
  UserRound,
  Warehouse,
} from 'lucide-vue-next'

import EntityDocumentsSection from '@/modules/documents/components/EntityDocumentsSection.vue'
import { ApiError } from '@/shared/api/http'
import type { AppSelectOption } from '@/shared/components/AppSelect.vue'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { usePermissions } from '@/shared/composables/usePermissions'
import { useToast } from '@/shared/composables/useToast'

import InventoryItemFormDrawer from '../components/InventoryItemFormDrawer.vue'
import { useUpdateInventoryItemMutation } from '../mutations/useItemMutations'
import { useInventoryCategoriesQuery } from '../queries/useCategoriesQuery'
import { useInventoryItemQuery, useItemBalancesQuery } from '../queries/useItemsQuery'
import { useInventoryMovementsQuery } from '../queries/useMovementsQuery'
import type { InventoryItemFormState } from '../types/items'
import {
  formatQuantity,
  formatSignedQuantity,
  mapInventoryErrorCode,
  movementTypeBadgeClass,
  stockStateBadgeClass,
  stockStateDotClass,
  validateInventoryItemForm,
} from '../validation/inventoryValidation'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const { can } = usePermissions()
const toast = useToast()

const id = computed(() => {
  const raw = route.params.id
  const value = Number(Array.isArray(raw) ? raw[0] : raw)
  return Number.isFinite(value) ? value : null
})

const { data, isLoading, isError, refetch } = useInventoryItemQuery(id)
const item = computed(() => data.value ?? null)

const { data: balancesData } = useItemBalancesQuery(id)
const { data: movementsData } = useInventoryMovementsQuery(
  computed(() => ({
    inventory_item_id: id.value ?? undefined,
    per_page: 15,
  })),
)
const { data: categoriesData } = useInventoryCategoriesQuery({})

const balances = computed(() => balancesData.value ?? [])
const movements = computed(() => movementsData.value?.data ?? [])

const kpis = computed(() => {
  let lowStock = 0
  let outOfStock = 0
  let totalOnHand = 0

  for (const balance of balances.value) {
    if (balance.stock_state === 'low') lowStock += 1
    if (balance.stock_state === 'out_of_stock') outOfStock += 1
    const qty = Number(balance.on_hand)
    if (Number.isFinite(qty)) totalOnHand += qty
  }

  return {
    warehouses: balances.value.length,
    lowStock,
    outOfStock,
    totalOnHand,
  }
})

const categoryFormOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('inventory.noCategory') },
  ...(categoriesData.value ?? [])
    .filter((c) => c.is_active)
    .map((c) => ({ value: c.id, label: c.name })),
])

const updateMutation = useUpdateInventoryItemMutation()
const isFormSubmitting = computed(() => updateMutation.isPending.value)
const drawerOpen = ref(false)
const formError = ref('')
const fieldErrors = reactive<Record<string, string>>({})
const form = reactive<InventoryItemFormState>({
  name: '',
  description: '',
  category_id: '',
  unit: 'piece',
  barcode: '',
  minimum_stock: '',
  is_active: true,
  notes: '',
})

function openEdit(): void {
  if (!item.value || !can('inventory.manage_items')) return
  Object.assign(form, {
    name: item.value.name,
    description: item.value.description ?? '',
    category_id: item.value.category?.id ?? '',
    unit: item.value.unit as InventoryItemFormState['unit'],
    barcode: item.value.barcode ?? '',
    minimum_stock: item.value.minimum_stock != null ? String(item.value.minimum_stock) : '',
    is_active: item.value.is_active,
    notes: item.value.notes ?? '',
  })
  formError.value = ''
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  drawerOpen.value = true
}

async function submitForm(): Promise<void> {
  if (!item.value) return
  formError.value = ''
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  Object.assign(fieldErrors, validateInventoryItemForm(form))
  if (Object.keys(fieldErrors).length) return

  try {
    await updateMutation.mutateAsync({
      id: item.value.id,
      payload: {
        name: form.name.trim(),
        description: form.description.trim() || null,
        category_id: form.category_id === '' ? null : Number(form.category_id),
        unit: form.unit,
        barcode: form.barcode.trim() || null,
        minimum_stock: form.minimum_stock.trim() === '' ? null : form.minimum_stock.trim(),
        is_active: form.is_active,
        notes: form.notes.trim() || null,
      },
    })
    toast.success(t('inventory.toasts.itemUpdated'))
    drawerOpen.value = false
    await refetch()
  } catch (error) {
    if (error instanceof ApiError) {
      const mapped = mapInventoryErrorCode(error.code)
      formError.value =
        mapped !== 'generic'
          ? t(`inventory.errors.${mapped}`)
          : error.message || t('inventory.errors.generic')
    } else {
      formError.value = t('inventory.errors.generic')
    }
  }
}

function assignForm(next: InventoryItemFormState): void {
  Object.assign(form, next)
}
</script>

<template>
  <div class="mx-auto max-w-[1200px] space-y-5">
    <div class="flex flex-wrap items-center gap-3">
      <button
        type="button"
        class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-brand-border bg-brand-surface px-3 text-sm font-semibold text-brand-text transition hover:bg-brand-bg"
        @click="router.push('/app/inventory/items')"
      >
        <ArrowRight class="h-4 w-4" />
        {{ t('inventory.items.backToList') }}
      </button>
    </div>

    <div
      v-if="isLoading"
      class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center text-sm text-brand-text-muted"
    >
      {{ t('inventory.loadingDetails') }}
    </div>

    <div
      v-else-if="isError || !item"
      class="rounded-2xl border border-red-200 bg-red-50 p-10 text-center"
    >
      <p class="text-sm text-red-700">{{ t('inventory.errors.loadDetails') }}</p>
      <button
        type="button"
        class="mt-3 text-sm font-semibold text-brand-primary-dark underline"
        @click="() => refetch()"
      >
        {{ t('inventory.retry') }}
      </button>
    </div>

    <template v-else>
      <section class="rounded-2xl border border-brand-border bg-brand-surface px-5 py-5 sm:px-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
              <p
                class="font-mono text-xs font-semibold tracking-wide text-brand-text-muted"
                dir="ltr"
              >
                {{ item.item_number }}
              </p>
              <span
                class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold tracking-wide text-brand-text ring-1 ring-inset"
                :class="
                  item.is_active
                    ? 'bg-emerald-100 ring-emerald-300/80'
                    : 'bg-neutral-100 ring-neutral-300/80'
                "
              >
                <span
                  class="h-1.5 w-1.5 shrink-0 rounded-full"
                  :class="item.is_active ? 'bg-emerald-500' : 'bg-neutral-400'"
                  aria-hidden="true"
                />
                {{ item.is_active ? t('inventory.status.active') : t('inventory.status.inactive') }}
              </span>
            </div>
            <h2 class="mt-2 text-[1.65rem] font-bold leading-snug text-brand-text sm:text-[1.85rem]">
              {{ item.name }}
            </h2>
            <p class="mt-2 text-sm text-brand-text-secondary">
              <span class="font-semibold text-brand-text">
                {{ item.category?.name ?? t('inventory.noCategory') }}
              </span>
              <span class="mx-1.5 text-brand-text-muted">·</span>
              {{ t(`inventory.units.${item.unit}`) }}
              <template v-if="item.barcode">
                <span class="mx-1.5 text-brand-text-muted">·</span>
                <span dir="ltr">{{ item.barcode }}</span>
              </template>
            </p>
          </div>

          <PermissionGuard
            v-if="can('inventory.manage_items')"
            permission="inventory.manage_items"
          >
            <button
              type="button"
              class="inline-flex h-10 items-center gap-2 rounded-xl border border-brand-border bg-brand-surface px-4 text-sm font-semibold text-brand-primary-dark transition hover:bg-brand-primary-soft"
              @click="openEdit"
            >
              <Pencil class="h-4 w-4" />
              {{ t('inventory.actions.edit') }}
            </button>
          </PermissionGuard>
        </div>
      </section>

      <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_320px]">
        <div class="space-y-5">
          <section class="rounded-2xl border border-brand-border bg-brand-surface">
            <header class="border-b border-brand-border px-5 py-4">
              <h3 class="text-sm font-bold text-brand-text">
                {{ t('inventory.items.detailsSummary') }}
              </h3>
            </header>
            <dl class="grid gap-0 sm:grid-cols-2">
              <div class="flex gap-3 border-b border-brand-border/80 px-5 py-4 sm:border-e">
                <span
                  class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-bg text-brand-primary"
                >
                  <Tag class="h-4 w-4" :stroke-width="1.75" />
                </span>
                <div class="min-w-0">
                  <dt class="text-xs font-semibold text-brand-text-muted">
                    {{ t('inventory.fields.category') }}
                  </dt>
                  <dd class="mt-1 text-sm font-semibold text-brand-text">
                    {{ item.category?.name || '—' }}
                  </dd>
                </div>
              </div>

              <div class="flex gap-3 border-b border-brand-border/80 px-5 py-4">
                <span
                  class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-bg text-brand-primary"
                >
                  <Package class="h-4 w-4" :stroke-width="1.75" />
                </span>
                <div class="min-w-0">
                  <dt class="text-xs font-semibold text-brand-text-muted">
                    {{ t('inventory.fields.unit') }}
                  </dt>
                  <dd class="mt-1 text-sm font-semibold text-brand-text">
                    {{ t(`inventory.units.${item.unit}`) }}
                  </dd>
                </div>
              </div>

              <div class="flex gap-3 border-b border-brand-border/80 px-5 py-4 sm:border-e">
                <span
                  class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-bg text-brand-primary"
                >
                  <Barcode class="h-4 w-4" :stroke-width="1.75" />
                </span>
                <div class="min-w-0">
                  <dt class="text-xs font-semibold text-brand-text-muted">
                    {{ t('inventory.fields.barcode') }}
                  </dt>
                  <dd class="mt-1 text-sm font-semibold text-brand-text" dir="ltr">
                    {{ item.barcode || '—' }}
                  </dd>
                </div>
              </div>

              <div class="flex gap-3 border-b border-brand-border/80 px-5 py-4">
                <span
                  class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-bg text-brand-primary"
                >
                  <Package class="h-4 w-4" :stroke-width="1.75" />
                </span>
                <div class="min-w-0">
                  <dt class="text-xs font-semibold text-brand-text-muted">
                    {{ t('inventory.fields.minimumStock') }}
                  </dt>
                  <dd class="mt-1 text-sm font-semibold text-brand-text" dir="ltr">
                    {{ formatQuantity(item.minimum_stock) }}
                  </dd>
                </div>
              </div>

              <div class="flex gap-3 px-5 py-4 sm:col-span-2">
                <span
                  class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-bg text-brand-primary"
                >
                  <UserRound class="h-4 w-4" :stroke-width="1.75" />
                </span>
                <div class="min-w-0">
                  <dt class="text-xs font-semibold text-brand-text-muted">
                    {{ t('inventory.fields.createdBy') }}
                  </dt>
                  <dd class="mt-1 text-sm font-semibold text-brand-text">
                    {{ item.created_by?.name || '—' }}
                  </dd>
                </div>
              </div>
            </dl>
          </section>

          <section
            v-if="item.description"
            class="rounded-2xl border border-brand-border bg-brand-surface"
          >
            <header class="flex items-center gap-2 border-b border-brand-border px-5 py-4">
              <FileText class="h-4 w-4 text-brand-primary" :stroke-width="1.75" />
              <h3 class="text-sm font-bold text-brand-text">
                {{ t('inventory.fields.description') }}
              </h3>
            </header>
            <p class="whitespace-pre-wrap px-5 py-4 text-sm leading-relaxed text-brand-text">
              {{ item.description }}
            </p>
          </section>

          <section
            v-if="item.notes"
            class="rounded-2xl border border-brand-border bg-brand-surface"
          >
            <header class="flex items-center gap-2 border-b border-brand-border px-5 py-4">
              <FileText class="h-4 w-4 text-brand-primary" :stroke-width="1.75" />
              <h3 class="text-sm font-bold text-brand-text">{{ t('inventory.fields.notes') }}</h3>
            </header>
            <p class="whitespace-pre-wrap px-5 py-4 text-sm leading-relaxed text-brand-text-secondary">
              {{ item.notes }}
            </p>
          </section>

          <section class="rounded-2xl border border-brand-border bg-brand-surface">
            <header class="flex items-center gap-2 border-b border-brand-border px-5 py-4">
              <Warehouse class="h-4 w-4 text-brand-primary" :stroke-width="1.75" />
              <h3 class="text-sm font-bold text-brand-text">
                {{ t('inventory.sections.balancesByWarehouse') }}
              </h3>
            </header>

            <p
              v-if="!balances.length"
              class="px-5 py-8 text-center text-sm text-brand-text-muted"
            >
              {{ t('inventory.balances.empty') }}
            </p>
            <div v-else class="overflow-x-auto">
              <table class="min-w-full border-separate border-spacing-0 text-sm">
                <thead>
                  <tr class="bg-[#F4F6F5]">
                    <th
                      v-for="key in ['warehouse', 'onHand', 'stockState']"
                      :key="key"
                      class="whitespace-nowrap border-b border-brand-border px-5 py-3 text-center text-xs font-bold text-brand-text"
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
                      class="max-w-[14rem] whitespace-nowrap border-b border-brand-border/80 px-5 py-3 text-center transition-colors group-hover:bg-[#EDF6F1]"
                    >
                      <RouterLink
                        v-if="balance.warehouse"
                        :to="`/app/warehouses/${balance.warehouse.id}`"
                        class="inline-flex max-w-full items-center justify-center gap-2"
                        :title="balance.warehouse.name"
                      >
                        <span
                          class="inline-flex shrink-0 items-center rounded-lg border border-brand-border bg-brand-bg px-2 py-1 font-mono text-[11px] font-bold text-brand-text"
                          dir="ltr"
                        >
                          {{ balance.warehouse.warehouse_number }}
                        </span>
                        <span
                          class="truncate font-semibold text-brand-text transition hover:text-brand-primary-dark hover:underline"
                        >
                          {{ balance.warehouse.name }}
                        </span>
                      </RouterLink>
                      <span v-else class="text-brand-text">—</span>
                    </td>
                    <td
                      class="whitespace-nowrap border-b border-brand-border/80 px-5 py-3 text-center font-semibold text-brand-text transition-colors group-hover:bg-[#EDF6F1]"
                      dir="ltr"
                    >
                      {{ formatQuantity(balance.on_hand) }}
                    </td>
                    <td
                      class="whitespace-nowrap border-b border-brand-border/80 px-5 py-3 text-center transition-colors group-hover:bg-[#EDF6F1]"
                    >
                      <span
                        class="inline-flex min-w-[6.25rem] items-center justify-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-bold shadow-sm"
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
          </section>

          <section class="rounded-2xl border border-brand-border bg-brand-surface">
            <header
              class="flex flex-wrap items-center justify-between gap-3 border-b border-brand-border px-5 py-4"
            >
              <h3 class="text-sm font-bold text-brand-text">
                {{ t('inventory.sections.recentMovements') }}
              </h3>
              <RouterLink
                to="/app/inventory/movements"
                class="text-xs font-semibold text-brand-primary-dark hover:underline"
              >
                {{ t('inventory.items.viewMovements') }}
              </RouterLink>
            </header>

            <p
              v-if="!movements.length"
              class="px-5 py-8 text-center text-sm text-brand-text-muted"
            >
              {{ t('inventory.movements.empty') }}
            </p>
            <ul v-else class="divide-y divide-brand-border/80">
              <li
                v-for="movement in movements"
                :key="movement.id"
                class="flex flex-wrap items-center justify-between gap-3 px-5 py-3.5 transition hover:bg-[#EDF6F1]/60"
              >
                <div class="flex min-w-0 flex-wrap items-center gap-2">
                  <span
                    class="inline-flex items-center rounded-lg border border-brand-border bg-brand-bg px-2 py-1 font-mono text-[11px] font-bold text-brand-text"
                    dir="ltr"
                  >
                    {{ movement.movement_number }}
                  </span>
                  <span
                    class="inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-bold"
                    :class="movementTypeBadgeClass(movement.type)"
                  >
                    {{ t(`inventory.movementType.${movement.type}`) }}
                  </span>
                  <span class="truncate text-sm font-semibold text-brand-text">
                    {{ movement.warehouse?.name ?? '—' }}
                  </span>
                </div>
                <span
                  class="shrink-0 text-sm font-bold tabular-nums text-brand-text"
                  dir="ltr"
                >
                  {{ formatSignedQuantity(movement.quantity, movement.direction) }}
                </span>
              </li>
            </ul>
          </section>

          <EntityDocumentsSection
            linkable-type="inventory_item"
            :linkable-id="item.id"
            :link-label="`${item.item_number} — ${item.name}`"
          />
        </div>

        <aside class="space-y-5 xl:sticky xl:top-4 xl:self-start">
          <section class="rounded-2xl border border-brand-border bg-brand-surface">
            <header class="border-b border-brand-border px-5 py-4">
              <h3 class="text-sm font-bold text-brand-text">
                {{ t('inventory.items.kpiTitle') }}
              </h3>
              <p class="mt-1 text-xs text-brand-text-secondary">
                {{ t('inventory.items.kpiHint') }}
              </p>
            </header>
            <div class="space-y-3 px-4 py-4">
              <div class="rounded-xl border border-brand-border bg-brand-bg/50 px-4 py-3">
                <p class="text-xs font-semibold text-brand-text-muted">
                  {{ t('inventory.items.warehousesCount') }}
                </p>
                <p class="mt-1 text-2xl font-bold tabular-nums text-brand-text">
                  {{ kpis.warehouses }}
                </p>
              </div>
              <div class="rounded-xl border border-brand-border bg-brand-bg/50 px-4 py-3">
                <p class="text-xs font-semibold text-brand-text-muted">
                  {{ t('inventory.items.totalOnHand') }}
                </p>
                <p class="mt-1 text-2xl font-bold tabular-nums text-brand-text">
                  {{ formatQuantity(kpis.totalOnHand) }}
                </p>
              </div>
              <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
                <p class="text-xs font-semibold text-brand-text-muted">
                  {{ t('inventory.kpis.lowStock') }}
                </p>
                <p class="mt-1 text-2xl font-bold tabular-nums text-brand-text">
                  {{ kpis.lowStock }}
                </p>
              </div>
              <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3">
                <p class="text-xs font-semibold text-brand-text-muted">
                  {{ t('inventory.kpis.outOfStock') }}
                </p>
                <p class="mt-1 text-2xl font-bold tabular-nums text-brand-text">
                  {{ kpis.outOfStock }}
                </p>
              </div>
            </div>
          </section>
        </aside>
      </div>
    </template>

    <InventoryItemFormDrawer
      :open="drawerOpen"
      :editing="item"
      :form="form"
      :form-error="formError"
      :field-errors="fieldErrors"
      :submitting="isFormSubmitting"
      :category-options="categoryFormOptions"
      @close="drawerOpen = false"
      @submit="submitForm"
      @update:form="assignForm"
    />
  </div>
</template>
