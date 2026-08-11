<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import {
  ChevronLeft,
  ChevronRight,
  FolderTree,
  Pencil,
  Plus,
  Search,
  Trash2,
} from 'lucide-vue-next'

import { useEmployeesQuery } from '@/modules/employees/queries/useEmployeesQuery'
import { useWarehousesQuery } from '@/modules/inventory/queries/useWarehousesQuery'
import { useOrganizationUnitsFlatQuery } from '@/modules/organization/queries/useOrganizationUnitsQuery'
import { ApiError } from '@/shared/api/http'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { useConfirm } from '@/shared/composables/useConfirm'
import { usePermissions } from '@/shared/composables/usePermissions'
import { useToast } from '@/shared/composables/useToast'

import AssetCategoriesManagerDrawer from '../components/AssetCategoriesManagerDrawer.vue'
import AssetFormDrawer from '../components/AssetFormDrawer.vue'
import {
  useCreateAssetMutation,
  useDeleteAssetMutation,
  useUpdateAssetMutation,
} from '../mutations/useAssetMutations'
import { useAssetCategoriesQuery } from '../queries/useCategoriesQuery'
import { useAssetsQuery } from '../queries/useAssetsQuery'
import type { Asset, AssetFormState, AssetStatus, ListAssetsParams } from '../types/assets'
import { ASSET_STATUSES } from '../types/assets'
import {
  assetStatusBadgeClass,
  canDeleteAsset,
  canEditAsset,
  canShowCategoriesButton,
  canShowCreateAssetCta,
  emptyAssetForm,
  filterAssetsListParams,
  mapAssetErrorCode,
  resolveAssetsListState,
  validateAssetForm,
} from '../validation/assetValidation'

const { t } = useI18n()
const router = useRouter()
const { permissions } = usePermissions()
const toast = useToast()
const { confirm } = useConfirm()

const filters = reactive({
  search: '',
  status: '' as AssetStatus | '',
  category_id: '' as number | '',
  warehouse_id: '' as number | '',
  organization_unit_id: '' as number | '',
  employee_id: '' as number | '',
  page: 1,
  per_page: 15,
})

const params = computed<ListAssetsParams>(() => filterAssetsListParams(filters))

const { data, isLoading, isError, refetch } = useAssetsQuery(params)
const assets = computed(() => data.value?.data ?? [])
const meta = computed(() => data.value?.meta)
const listState = computed(() =>
  resolveAssetsListState({
    isLoading: isLoading.value,
    isError: isError.value,
    count: assets.value.length,
  }),
)

const { data: categoriesData } = useAssetCategoriesQuery({})
const { data: warehousesData } = useWarehousesQuery(
  computed(() => ({ is_active: true, per_page: 100 })),
)
const { data: orgUnitsData } = useOrganizationUnitsFlatQuery({ status: 'active' })
const { data: employeesData } = useEmployeesQuery(
  computed(() => ({ status: 'active' as const, per_page: 100 })),
)

const categoryFilterOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('assets.filters.allCategories') },
  ...(categoriesData.value ?? []).map((c) => ({ value: c.id, label: c.name })),
])
const categoryFormOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('assets.noCategory') },
  ...(categoriesData.value ?? [])
    .filter((c) => c.is_active)
    .map((c) => ({ value: c.id, label: c.name })),
])
const statusOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('assets.filters.allStatuses') },
  ...ASSET_STATUSES.map((status) => ({
    value: status,
    label: t(`assets.status.${status}`),
  })),
])
const warehouseFilterOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('assets.filters.allWarehouses') },
  ...(warehousesData.value?.data ?? []).map((w) => ({
    value: w.id,
    label: w.name,
    hint: w.warehouse_number ?? undefined,
  })),
])
const warehouseFormOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('assets.noWarehouse') },
  ...(warehousesData.value?.data ?? []).map((w) => ({
    value: w.id,
    label: w.name,
    hint: w.warehouse_number ?? undefined,
  })),
])
const orgUnitFilterOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('assets.filters.allOrgUnits') },
  ...(orgUnitsData.value?.data ?? []).map((u) => ({ value: u.id, label: u.name, hint: u.code })),
])
const orgUnitFormOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('assets.noOrgUnit') },
  ...(orgUnitsData.value?.data ?? []).map((u) => ({ value: u.id, label: u.name, hint: u.code })),
])
const employeeFilterOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('assets.filters.allEmployees') },
  ...(employeesData.value?.data ?? []).map((e) => ({
    value: e.id,
    label: e.full_name,
    hint: e.employee_number,
  })),
])

watch(
  () => [
    filters.search,
    filters.status,
    filters.category_id,
    filters.warehouse_id,
    filters.organization_unit_id,
    filters.employee_id,
  ],
  () => {
    filters.page = 1
  },
)

const createMutation = useCreateAssetMutation()
const updateMutation = useUpdateAssetMutation()
const deleteMutation = useDeleteAssetMutation()

const drawerOpen = ref(false)
const categoriesOpen = ref(false)
const editing = ref<Asset | null>(null)
const formError = ref('')
const fieldErrors = reactive<Record<string, string>>({})
const form = reactive<AssetFormState>(emptyAssetForm())

const submitting = computed(
  () => createMutation.isPending.value || updateMutation.isPending.value,
)

const showCreate = computed(() => canShowCreateAssetCta(permissions.value))
const showCategories = computed(() => canShowCategoriesButton(permissions.value))

function apiMessage(error: unknown): string {
  if (!(error instanceof ApiError)) return t('assets.errors.generic')
  const mapped = mapAssetErrorCode(error.code)
  if (mapped !== 'generic') return t(`assets.errors.${mapped}`)
  return error.message || t('assets.errors.generic')
}

function openCreate(): void {
  editing.value = null
  Object.assign(form, emptyAssetForm())
  formError.value = ''
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  drawerOpen.value = true
}

function openEdit(asset: Asset): void {
  editing.value = asset
  Object.assign(form, {
    name: asset.name,
    description: asset.description ?? '',
    category_id: asset.category?.id ?? '',
    serial_number: asset.serial_number ?? '',
    barcode: asset.barcode ?? '',
    condition: (asset.condition as AssetFormState['condition']) || 'good',
    warehouse_id: asset.warehouse?.id ?? '',
    organization_unit_id: asset.organization_unit?.id ?? '',
    purchase_value: asset.purchase_value != null ? String(asset.purchase_value) : '',
    acquisition_date: asset.acquisition_date ?? '',
    notes: asset.notes ?? '',
  })
  formError.value = ''
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  drawerOpen.value = true
}

function toPayload() {
  return {
    name: form.name.trim(),
    description: form.description.trim() || null,
    category_id: form.category_id === '' ? null : Number(form.category_id),
    serial_number: form.serial_number.trim() || null,
    barcode: form.barcode.trim() || null,
    condition: form.condition || null,
    warehouse_id: form.warehouse_id === '' ? null : Number(form.warehouse_id),
    organization_unit_id:
      form.organization_unit_id === '' ? null : Number(form.organization_unit_id),
    purchase_value: form.purchase_value.trim() === '' ? null : form.purchase_value.trim(),
    acquisition_date: form.acquisition_date.trim() || null,
    notes: form.notes.trim() || null,
  }
}

async function submitForm(): Promise<void> {
  formError.value = ''
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  Object.assign(fieldErrors, validateAssetForm(form))
  if (Object.keys(fieldErrors).length) return

  try {
    if (editing.value) {
      await updateMutation.mutateAsync({ id: editing.value.id, payload: toPayload() })
      toast.success(t('assets.toasts.assetUpdated'))
    } else {
      await createMutation.mutateAsync(toPayload())
      toast.success(t('assets.toasts.assetCreated'))
    }
    drawerOpen.value = false
  } catch (error) {
    formError.value = apiMessage(error)
  }
}

async function removeAsset(asset: Asset): Promise<void> {
  if (!canDeleteAsset(permissions.value)) return
  const ok = await confirm({
    title: t('assets.confirm.deleteAsset.title'),
    message: t('assets.confirm.deleteAsset.body'),
    confirmLabel: t('assets.actions.delete'),
    variant: 'danger',
  })
  if (!ok) return
  try {
    await deleteMutation.mutateAsync(asset.id)
    toast.success(t('assets.toasts.assetDeleted'))
  } catch (error) {
    toast.error(apiMessage(error))
  }
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <h2 class="text-[1.75rem] font-bold">{{ t('assets.list.title') }}</h2>
        <p class="mt-1.5 text-sm text-brand-text-secondary">{{ t('assets.list.subtitle') }}</p>
      </div>
      <div class="flex flex-wrap gap-2">
        <PermissionGuard v-if="showCategories" permission="assets.update">
          <button
            type="button"
            class="inline-flex h-11 items-center gap-2 rounded-xl border px-4 text-sm font-semibold"
            @click="categoriesOpen = true"
          >
            <FolderTree class="h-4 w-4" />
            {{ t('assets.categories.nav') }}
          </button>
        </PermissionGuard>
        <PermissionGuard v-if="showCreate" permission="assets.create">
          <button
            type="button"
            class="inline-flex h-11 items-center gap-2 rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white"
            @click="openCreate"
          >
            <Plus class="h-4 w-4" />
            {{ t('assets.list.createCta') }}
          </button>
        </PermissionGuard>
      </div>
    </div>

    <div class="flex flex-wrap items-center gap-3 rounded-2xl border bg-brand-surface p-4">
      <div class="relative min-w-48 flex-1">
        <Search
          class="pointer-events-none absolute inset-s-3 top-1/2 h-4 w-4 -translate-y-1/2 text-brand-text-muted"
        />
        <input
          v-model="filters.search"
          type="search"
          class="h-11 w-full rounded-xl border pe-3 ps-10 text-sm"
          :placeholder="t('assets.list.searchPlaceholder')"
        />
      </div>
      <AppSelect v-model="filters.status" :options="statusOptions" />
      <AppSelect v-model="filters.category_id" :options="categoryFilterOptions" searchable />
      <AppSelect v-model="filters.warehouse_id" :options="warehouseFilterOptions" searchable />
      <AppSelect v-model="filters.organization_unit_id" :options="orgUnitFilterOptions" searchable />
      <AppSelect v-model="filters.employee_id" :options="employeeFilterOptions" searchable />
    </div>

    <div
      v-if="listState === 'loading'"
      class="rounded-2xl border p-10 text-center text-sm text-brand-text-muted"
    >
      {{ t('assets.loading') }}
    </div>
    <div
      v-else-if="listState === 'error'"
      class="rounded-2xl border border-red-200 bg-red-50 p-10 text-center"
    >
      <p class="text-sm text-red-700">{{ t('assets.errors.load') }}</p>
      <button type="button" class="mt-3 underline" @click="() => refetch()">
        {{ t('assets.retry') }}
      </button>
    </div>
    <div
      v-else-if="listState === 'empty'"
      class="rounded-2xl border p-10 text-center text-sm text-brand-text-muted"
    >
      {{ t('assets.list.empty') }}
    </div>
    <template v-else>
      <div class="hidden overflow-hidden rounded-2xl border bg-brand-surface md:block">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="bg-[#F4F6F5]">
              <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">
                {{ t('assets.columns.number') }}
              </th>
              <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">
                {{ t('assets.columns.name') }}
              </th>
              <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">
                {{ t('assets.columns.category') }}
              </th>
              <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">
                {{ t('assets.columns.serial') }}
              </th>
              <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">
                {{ t('assets.columns.status') }}
              </th>
              <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">
                {{ t('assets.columns.warehouse') }}
              </th>
              <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">
                {{ t('assets.columns.employee') }}
              </th>
              <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">
                {{ t('assets.columns.orgUnit') }}
              </th>
              <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">
                {{ t('assets.columns.actions') }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="asset in assets"
              :key="asset.id"
              class="cursor-pointer border-t hover:bg-brand-bg/60"
              @click="router.push(`/app/assets/${asset.id}`)"
            >
              <td class="px-5 py-3 font-mono text-xs">{{ asset.asset_number }}</td>
              <td class="px-5 py-3 font-semibold">{{ asset.name }}</td>
              <td class="px-5 py-3">{{ asset.category?.name || '—' }}</td>
              <td class="px-5 py-3">{{ asset.serial_number || '—' }}</td>
              <td class="px-5 py-3">
                <span
                  class="rounded-full px-2 py-0.5 text-xs font-semibold"
                  :class="assetStatusBadgeClass(asset.status)"
                >
                  {{ t(`assets.status.${asset.status}`) }}
                </span>
              </td>
              <td class="px-5 py-3">{{ asset.warehouse?.name || '—' }}</td>
              <td class="px-5 py-3">{{ asset.current_custody?.employee?.full_name || '—' }}</td>
              <td class="px-5 py-3">{{ asset.organization_unit?.name || '—' }}</td>
              <td class="px-5 py-3" @click.stop>
                <div class="flex items-center gap-1">
                  <PermissionGuard v-if="canEditAsset(permissions)" permission="assets.update">
                    <button
                      type="button"
                      class="rounded-lg p-2 hover:bg-brand-bg"
                      @click="openEdit(asset)"
                    >
                      <Pencil class="h-4 w-4" />
                    </button>
                  </PermissionGuard>
                  <PermissionGuard v-if="canDeleteAsset(permissions)" permission="assets.delete">
                    <button
                      type="button"
                      class="rounded-lg p-2 text-red-700 hover:bg-red-50"
                      @click="removeAsset(asset)"
                    >
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
          v-for="asset in assets"
          :key="asset.id"
          class="rounded-2xl border bg-brand-surface p-4"
          @click="router.push(`/app/assets/${asset.id}`)"
        >
          <div class="flex items-start justify-between gap-2">
            <div>
              <p class="font-mono text-xs text-brand-text-muted">{{ asset.asset_number }}</p>
              <h3 class="font-bold">{{ asset.name }}</h3>
              <p class="mt-1 text-sm text-brand-text-secondary">
                {{ asset.category?.name || t('assets.noCategory') }}
              </p>
            </div>
            <span
              class="rounded-full px-2 py-0.5 text-xs font-semibold"
              :class="assetStatusBadgeClass(asset.status)"
            >
              {{ t(`assets.status.${asset.status}`) }}
            </span>
          </div>
          <dl class="mt-3 grid grid-cols-2 gap-2 text-xs text-brand-text-secondary">
            <div>
              <dt>{{ t('assets.columns.warehouse') }}</dt>
              <dd class="font-medium text-brand-text">{{ asset.warehouse?.name || '—' }}</dd>
            </div>
            <div>
              <dt>{{ t('assets.columns.employee') }}</dt>
              <dd class="font-medium text-brand-text">
                {{ asset.current_custody?.employee?.full_name || '—' }}
              </dd>
            </div>
          </dl>
        </article>
      </div>

      <div v-if="meta && meta.last_page > 1" class="flex items-center justify-between gap-3">
        <button
          type="button"
          class="inline-flex items-center gap-1 rounded-xl border px-3 py-2 text-sm disabled:opacity-40"
          :disabled="filters.page <= 1"
          @click="filters.page -= 1"
        >
          <ChevronRight class="h-4 w-4" />
          {{ t('assets.prev') }}
        </button>
        <span class="text-sm text-brand-text-muted">{{ filters.page }} / {{ meta.last_page }}</span>
        <button
          type="button"
          class="inline-flex items-center gap-1 rounded-xl border px-3 py-2 text-sm disabled:opacity-40"
          :disabled="filters.page >= meta.last_page"
          @click="filters.page += 1"
        >
          {{ t('assets.next') }}
          <ChevronLeft class="h-4 w-4" />
        </button>
      </div>
    </template>

    <AssetFormDrawer
      :open="drawerOpen"
      :editing="editing"
      :form="form"
      :form-error="formError"
      :field-errors="fieldErrors"
      :submitting="submitting"
      :category-options="categoryFormOptions"
      :warehouse-options="warehouseFormOptions"
      :org-unit-options="orgUnitFormOptions"
      @close="drawerOpen = false"
      @submit="submitForm"
      @update:form="Object.assign(form, $event)"
    />

    <AssetCategoriesManagerDrawer :open="categoriesOpen" @close="categoriesOpen = false" />
  </div>
</template>
