<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { ChevronLeft, ChevronRight, FolderTree, Pencil, Plus, Power, PowerOff, Search, Trash2 } from 'lucide-vue-next'

import { ApiError } from '@/shared/api/http'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { useConfirm } from '@/shared/composables/useConfirm'
import { usePermissions } from '@/shared/composables/usePermissions'
import { useToast } from '@/shared/composables/useToast'

import InventoryCategoriesManagerDrawer from '../components/InventoryCategoriesManagerDrawer.vue'
import InventoryItemFormDrawer from '../components/InventoryItemFormDrawer.vue'
import {
  useActivateInventoryItemMutation,
  useCreateInventoryItemMutation,
  useDeactivateInventoryItemMutation,
  useDeleteInventoryItemMutation,
  useUpdateInventoryItemMutation,
} from '../mutations/useItemMutations'
import { useInventoryCategoriesQuery } from '../queries/useCategoriesQuery'
import { useInventoryItemsQuery } from '../queries/useItemsQuery'
import type { InventoryItem, InventoryItemFormState, ListInventoryItemsParams } from '../types/items'
import {
  formatQuantity,
  mapInventoryErrorCode,
  resolveInventoryListState,
  validateInventoryItemForm,
} from '../validation/inventoryValidation'

const { t } = useI18n()
const router = useRouter()
const { can } = usePermissions()
const toast = useToast()
const { confirm } = useConfirm()

const filters = reactive({
  search: '',
  category_id: '' as number | '',
  is_active: '' as '' | '1' | '0',
  page: 1,
  per_page: 15,
})

const params = computed<ListInventoryItemsParams>(() => ({
  search: filters.search || undefined,
  category_id: filters.category_id,
  is_active: filters.is_active === '' ? '' : filters.is_active === '1',
  page: filters.page,
  per_page: filters.per_page,
}))

const { data, isLoading, isError, refetch } = useInventoryItemsQuery(params)
const items = computed(() => data.value?.data ?? [])
const meta = computed(() => data.value?.meta)
const listState = computed(() =>
  resolveInventoryListState({
    isLoading: isLoading.value,
    isError: isError.value,
    count: items.value.length,
  }),
)

const { data: categoriesData } = useInventoryCategoriesQuery({})
const categoryFilterOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('inventory.filters.allCategories') },
  ...(categoriesData.value ?? []).map((c) => ({ value: c.id, label: c.name })),
])
const categoryFormOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('inventory.noCategory') },
  ...(categoriesData.value ?? [])
    .filter((c) => c.is_active)
    .map((c) => ({ value: c.id, label: c.name })),
])
const statusOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('inventory.filters.allStatuses') },
  { value: '1', label: t('inventory.status.active') },
  { value: '0', label: t('inventory.status.inactive') },
])

watch(
  () => [filters.search, filters.category_id, filters.is_active],
  () => {
    filters.page = 1
  },
)

const createMutation = useCreateInventoryItemMutation()
const updateMutation = useUpdateInventoryItemMutation()
const activateMutation = useActivateInventoryItemMutation()
const deactivateMutation = useDeactivateInventoryItemMutation()
const deleteMutation = useDeleteInventoryItemMutation()

const drawerOpen = ref(false)
const categoriesOpen = ref(false)
const editing = ref<InventoryItem | null>(null)
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

const submitting = computed(
  () => createMutation.isPending.value || updateMutation.isPending.value,
)

function apiMessage(error: unknown): string {
  if (!(error instanceof ApiError)) return t('inventory.errors.generic')
  const mapped = mapInventoryErrorCode(error.code)
  if (mapped !== 'generic') return t(`inventory.errors.${mapped}`)
  return error.message || t('inventory.errors.generic')
}

function openCreate(): void {
  editing.value = null
  Object.assign(form, {
    name: '',
    description: '',
    category_id: '',
    unit: 'piece',
    barcode: '',
    minimum_stock: '',
    is_active: true,
    notes: '',
  })
  formError.value = ''
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  drawerOpen.value = true
}

function openEdit(item: InventoryItem): void {
  editing.value = item
  Object.assign(form, {
    name: item.name,
    description: item.description ?? '',
    category_id: item.category?.id ?? '',
    unit: item.unit as InventoryItemFormState['unit'],
    barcode: item.barcode ?? '',
    minimum_stock: item.minimum_stock != null ? String(item.minimum_stock) : '',
    is_active: item.is_active,
    notes: item.notes ?? '',
  })
  formError.value = ''
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  drawerOpen.value = true
}

async function submitForm(): Promise<void> {
  formError.value = ''
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  Object.assign(fieldErrors, validateInventoryItemForm(form))
  if (Object.keys(fieldErrors).length) return

  const payload = {
    name: form.name.trim(),
    description: form.description.trim() || null,
    category_id: form.category_id === '' ? null : Number(form.category_id),
    unit: form.unit,
    barcode: form.barcode.trim() || null,
    minimum_stock: form.minimum_stock.trim() === '' ? null : form.minimum_stock.trim(),
    is_active: form.is_active,
    notes: form.notes.trim() || null,
  }

  try {
    if (editing.value) {
      await updateMutation.mutateAsync({ id: editing.value.id, payload })
      toast.success(t('inventory.toasts.itemUpdated'))
    } else {
      await createMutation.mutateAsync(payload)
      toast.success(t('inventory.toasts.itemCreated'))
    }
    drawerOpen.value = false
  } catch (error) {
    formError.value = apiMessage(error)
  }
}

async function toggleActive(item: InventoryItem): Promise<void> {
  if (!can('inventory.manage_items')) return
  const next = !item.is_active
  const ok = await confirm({
    title: next ? t('inventory.confirm.activateItem.title') : t('inventory.confirm.deactivateItem.title'),
    message: next ? t('inventory.confirm.activateItem.body') : t('inventory.confirm.deactivateItem.body'),
    confirmLabel: next ? t('inventory.actions.activate') : t('inventory.actions.deactivate'),
    variant: 'warning',
  })
  if (!ok) return
  try {
    if (next) await activateMutation.mutateAsync(item.id)
    else await deactivateMutation.mutateAsync(item.id)
    toast.success(next ? t('inventory.toasts.itemActivated') : t('inventory.toasts.itemDeactivated'))
  } catch (error) {
    toast.error(apiMessage(error))
  }
}

async function removeItem(item: InventoryItem): Promise<void> {
  if (!can('inventory.manage_items')) return
  const ok = await confirm({
    title: t('inventory.confirm.deleteItem.title'),
    message: t('inventory.confirm.deleteItem.body'),
    confirmLabel: t('inventory.actions.delete'),
    variant: 'danger',
  })
  if (!ok) return
  try {
    await deleteMutation.mutateAsync(item.id)
    toast.success(t('inventory.toasts.itemDeleted'))
  } catch (error) {
    toast.error(apiMessage(error))
  }
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <h2 class="text-[1.75rem] font-bold">{{ t('inventory.items.title') }}</h2>
        <p class="mt-1.5 text-sm text-brand-text-secondary">{{ t('inventory.items.subtitle') }}</p>
      </div>
      <div class="flex flex-wrap gap-2">
        <PermissionGuard permission="inventory.manage_items">
          <button type="button" class="inline-flex h-11 items-center gap-2 rounded-xl border px-4 text-sm font-semibold" @click="categoriesOpen = true">
            <FolderTree class="h-4 w-4" />
            {{ t('inventory.nav.categories') }}
          </button>
          <button type="button" class="inline-flex h-11 items-center gap-2 rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white" @click="openCreate">
            <Plus class="h-4 w-4" />
            {{ t('inventory.items.createCta') }}
          </button>
        </PermissionGuard>
      </div>
    </div>

    <div class="flex flex-wrap items-center gap-3 rounded-2xl border bg-brand-surface p-4">
      <div class="relative min-w-48 flex-1">
        <Search class="pointer-events-none absolute inset-s-3 top-1/2 h-4 w-4 -translate-y-1/2 text-brand-text-muted" />
        <input
          v-model="filters.search"
          type="search"
          class="h-11 w-full rounded-xl border pe-3 ps-10 text-sm"
          :placeholder="t('inventory.items.searchPlaceholder')"
        />
      </div>
      <AppSelect v-model="filters.category_id" :options="categoryFilterOptions" searchable />
      <AppSelect v-model="filters.is_active" :options="statusOptions" />
    </div>

    <div v-if="listState === 'loading'" class="rounded-2xl border p-10 text-center text-sm text-brand-text-muted">
      {{ t('inventory.loading') }}
    </div>
    <div v-else-if="listState === 'error'" class="rounded-2xl border border-red-200 bg-red-50 p-10 text-center">
      <p class="text-sm text-red-700">{{ t('inventory.errors.load') }}</p>
      <button type="button" class="mt-3 underline" @click="() => refetch()">{{ t('inventory.retry') }}</button>
    </div>
    <div v-else-if="listState === 'empty'" class="rounded-2xl border p-10 text-center text-sm text-brand-text-muted">
      {{ t('inventory.items.empty') }}
    </div>
    <template v-else>
      <div class="hidden overflow-hidden rounded-2xl border bg-brand-surface md:block">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="bg-[#F4F6F5]">
              <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">{{ t('inventory.columns.number') }}</th>
              <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">{{ t('inventory.columns.name') }}</th>
              <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">{{ t('inventory.columns.category') }}</th>
              <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">{{ t('inventory.columns.unit') }}</th>
              <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">{{ t('inventory.columns.minimumStock') }}</th>
              <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">{{ t('inventory.columns.status') }}</th>
              <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">{{ t('inventory.columns.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="item in items"
              :key="item.id"
              class="cursor-pointer border-t hover:bg-brand-bg/60"
              @click="router.push(`/app/inventory/items/${item.id}`)"
            >
              <td class="px-5 py-3 font-mono text-xs">{{ item.item_number }}</td>
              <td class="px-5 py-3 font-semibold">{{ item.name }}</td>
              <td class="px-5 py-3">{{ item.category?.name || '—' }}</td>
              <td class="px-5 py-3">{{ t(`inventory.units.${item.unit}`) }}</td>
              <td class="px-5 py-3">{{ formatQuantity(item.minimum_stock) }}</td>
              <td class="px-5 py-3">
                <span
                  class="rounded-full px-2 py-0.5 text-xs font-semibold"
                  :class="item.is_active ? 'bg-emerald-50 text-emerald-800' : 'bg-neutral-100 text-neutral-600'"
                >
                  {{ item.is_active ? t('inventory.status.active') : t('inventory.status.inactive') }}
                </span>
              </td>
              <td class="px-5 py-3" @click.stop>
                <div class="flex items-center gap-1">
                  <PermissionGuard permission="inventory.manage_items">
                    <button type="button" class="rounded-lg p-2 hover:bg-brand-bg" @click="openEdit(item)">
                      <Pencil class="h-4 w-4" />
                    </button>
                    <button type="button" class="rounded-lg p-2 hover:bg-brand-bg" @click="toggleActive(item)">
                      <PowerOff v-if="item.is_active" class="h-4 w-4" />
                      <Power v-else class="h-4 w-4" />
                    </button>
                    <button type="button" class="rounded-lg p-2 text-red-700 hover:bg-red-50" @click="removeItem(item)">
                      <Trash2 class="h-4 w-4" />
                    </button>
                  </PermissionGuard>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="space-y-3 md:hidden">
        <article
          v-for="item in items"
          :key="item.id"
          class="rounded-2xl border bg-brand-surface p-4"
          @click="router.push(`/app/inventory/items/${item.id}`)"
        >
          <p class="font-mono text-xs text-brand-text-muted">{{ item.item_number }}</p>
          <h3 class="font-bold">{{ item.name }}</h3>
          <p class="mt-1 text-sm text-brand-text-secondary">{{ item.category?.name || t('inventory.noCategory') }}</p>
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

    <InventoryItemFormDrawer
      :open="drawerOpen"
      :editing="editing"
      :form="form"
      :form-error="formError"
      :field-errors="fieldErrors"
      :submitting="submitting"
      :category-options="categoryFormOptions"
      @close="drawerOpen = false"
      @submit="submitForm"
      @update:form="Object.assign(form, $event)"
    />

    <InventoryCategoriesManagerDrawer :open="categoriesOpen" @close="categoriesOpen = false" />
  </div>
</template>
