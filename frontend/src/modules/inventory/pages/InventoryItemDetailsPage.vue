<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { ArrowRight, Pencil } from 'lucide-vue-next'

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
  validateInventoryItemForm,
} from '../validation/inventoryValidation'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const { can } = usePermissions()
const toast = useToast()

const id = computed(() => Number(route.params.id))
const { data, isLoading, isError, refetch } = useInventoryItemQuery(id)
const item = computed(() => data.value ?? null)

const { data: balancesData } = useItemBalancesQuery(id)
const { data: movementsData } = useInventoryMovementsQuery(
  computed(() => ({ inventory_item_id: id.value, per_page: 15 })),
)
const { data: categoriesData } = useInventoryCategoriesQuery({})

const balances = computed(() => balancesData.value ?? [])
const movements = computed(() => movementsData.value?.data ?? [])
const categoryFormOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('inventory.noCategory') },
  ...(categoriesData.value ?? [])
    .filter((c) => c.is_active)
    .map((c) => ({ value: c.id, label: c.name })),
])

const updateMutation = useUpdateInventoryItemMutation()
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
  if (!item.value) return
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
  } catch (error) {
    if (error instanceof ApiError) {
      const mapped = mapInventoryErrorCode(error.code)
      formError.value =
        mapped !== 'generic' ? t(`inventory.errors.${mapped}`) : error.message || t('inventory.errors.generic')
    } else {
      formError.value = t('inventory.errors.generic')
    }
  }
}
</script>

<template>
  <div class="space-y-6">
    <button type="button" class="rounded-lg border px-3 py-2 text-sm" @click="router.push('/app/inventory/items')">
      <ArrowRight class="inline h-4 w-4" />
      {{ t('inventory.items.backToList') }}
    </button>

    <div v-if="isLoading" class="rounded-2xl border p-10 text-center">{{ t('inventory.loadingDetails') }}</div>
    <div v-else-if="isError || !item" class="rounded-2xl border p-10 text-center">
      {{ t('inventory.errors.loadDetails') }}
      <button type="button" class="ms-2 underline" @click="() => refetch()">{{ t('inventory.retry') }}</button>
    </div>

    <template v-else>
      <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
          <div class="flex flex-wrap items-center gap-2">
            <span class="font-mono text-sm">{{ item.item_number }}</span>
            <span
              class="rounded-full px-2 py-0.5 text-xs font-semibold"
              :class="item.is_active ? 'bg-emerald-50 text-emerald-800' : 'bg-neutral-100 text-neutral-600'"
            >
              {{ item.is_active ? t('inventory.status.active') : t('inventory.status.inactive') }}
            </span>
          </div>
          <h2 class="mt-2 text-2xl font-bold">{{ item.name }}</h2>
        </div>
        <PermissionGuard v-if="can('inventory.manage_items')" permission="inventory.manage_items">
          <button type="button" class="rounded-xl border px-4 py-2 text-sm font-semibold" @click="openEdit">
            <Pencil class="inline h-4 w-4" />
            {{ t('inventory.actions.edit') }}
          </button>
        </PermissionGuard>
      </div>

      <section>
        <h3 class="mb-3 font-bold">{{ t('inventory.sections.overview') }}</h3>
        <div class="grid gap-4 md:grid-cols-3">
          <div
            v-for="row in [
              { label: 'category', value: item.category?.name },
              { label: 'unit', value: t(`inventory.units.${item.unit}`) },
              { label: 'barcode', value: item.barcode },
              { label: 'minimumStock', value: formatQuantity(item.minimum_stock) },
              { label: 'createdBy', value: item.created_by?.name },
            ]"
            :key="row.label"
            class="rounded-2xl border bg-brand-surface p-4"
          >
            <p class="text-xs text-brand-text-muted">{{ t(`inventory.fields.${row.label}`) }}</p>
            <p class="mt-1 font-semibold">{{ row.value || '—' }}</p>
          </div>
        </div>
        <div class="mt-4 rounded-2xl border p-4">
          <p class="text-xs text-brand-text-muted">{{ t('inventory.fields.description') }}</p>
          <p class="mt-2 whitespace-pre-wrap">{{ item.description || '—' }}</p>
        </div>
      </section>

      <section class="rounded-2xl border p-5">
        <h3 class="mb-3 font-bold">{{ t('inventory.sections.balancesByWarehouse') }}</h3>
        <div v-if="!balances.length" class="text-sm text-brand-text-muted">{{ t('inventory.balances.empty') }}</div>
        <div v-else class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead>
              <tr class="text-xs text-brand-text-muted">
                <th class="py-2 pe-4 text-start">{{ t('inventory.columns.warehouse') }}</th>
                <th class="py-2 pe-4 text-start">{{ t('inventory.columns.onHand') }}</th>
                <th class="py-2 text-start">{{ t('inventory.columns.stockState') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="balance in balances" :key="balance.id" class="border-t">
                <td class="py-2 pe-4">
                  <RouterLink
                    v-if="balance.warehouse"
                    :to="`/app/warehouses/${balance.warehouse.id}`"
                    class="hover:underline"
                  >
                    {{ balance.warehouse.name }}
                  </RouterLink>
                </td>
                <td class="py-2 pe-4 font-semibold">{{ formatQuantity(balance.on_hand) }}</td>
                <td class="py-2">
                  <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="stockStateBadgeClass(balance.stock_state)">
                    {{ t(`inventory.stockState.${balance.stock_state}`) }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section class="rounded-2xl border p-5">
        <h3 class="mb-3 font-bold">{{ t('inventory.sections.recentMovements') }}</h3>
        <div v-if="!movements.length" class="text-sm text-brand-text-muted">{{ t('inventory.movements.empty') }}</div>
        <ul v-else class="space-y-2">
          <li
            v-for="movement in movements"
            :key="movement.id"
            class="flex flex-wrap items-center justify-between gap-2 rounded-xl border px-3 py-2 text-sm"
          >
            <div>
              <span class="font-mono text-xs">{{ movement.movement_number }}</span>
              <span class="ms-2 rounded-full px-2 py-0.5 text-xs font-semibold" :class="movementTypeBadgeClass(movement.type)">
                {{ t(`inventory.movementType.${movement.type}`) }}
              </span>
              <span class="ms-2">{{ movement.warehouse?.name }}</span>
            </div>
            <div class="font-semibold" :class="movement.direction === 'out' ? 'text-amber-800' : 'text-emerald-800'">
              {{ formatSignedQuantity(movement.quantity, movement.direction) }}
            </div>
          </li>
        </ul>
      </section>

      <EntityDocumentsSection
        linkable-type="inventory_item"
        :linkable-id="item.id"
        :link-label="`${item.item_number} — ${item.name}`"
      />
    </template>

    <InventoryItemFormDrawer
      :open="drawerOpen"
      :editing="item"
      :form="form"
      :form-error="formError"
      :field-errors="fieldErrors"
      :submitting="updateMutation.isPending.value"
      :category-options="categoryFormOptions"
      @close="drawerOpen = false"
      @submit="submitForm"
      @update:form="Object.assign(form, $event)"
    />
  </div>
</template>
