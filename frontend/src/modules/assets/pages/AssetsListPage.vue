<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRouter } from 'vue-router'
import {
  ChevronLeft,
  ChevronRight,
  FolderTree,
  Pencil,
  Plus,
  Search,
  Trash2,
} from 'lucide-vue-next'

import { listEmployees } from '@/modules/employees/api/employeesApi'
import { listWarehouses } from '@/modules/inventory/api/warehousesApi'
import { useOrganizationUnitsFlatQuery } from '@/modules/organization/queries/useOrganizationUnitsQuery'
import { ApiError } from '@/shared/api/http'
import AppMobileFilters from '@/shared/components/AppMobileFilters.vue'
import AppPageHeader from '@/shared/components/AppPageHeader.vue'
import AppRemoteSelect from '@/shared/components/AppRemoteSelect.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import { employeeSelectOption, toSelectId, warehouseSelectOption } from '@/shared/lookups/selectOptions'
import AppTooltip from '@/shared/components/AppTooltip.vue'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { useConfirm } from '@/shared/composables/useConfirm'
import { usePermissions } from '@/shared/composables/usePermissions'
import { useToast } from '@/shared/composables/useToast'
import { useDebouncedRef } from '@/shared/composables/useDebouncedRef'

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
  assetStatusDotClass,
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

const committedSearch = useDebouncedRef(() => filters.search)
const params = computed<ListAssetsParams>(() => filterAssetsListParams({
  ...filters,
  search: committedSearch.value,
}))

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
const { data: orgUnitsData } = useOrganizationUnitsFlatQuery({ status: 'active' })
const fetchActiveWarehouses = (params: { search?: string; page: number; per_page: number }) =>
  listWarehouses({ ...params, is_active: true })
const fetchActiveEmployees = (params: { search?: string; page: number; per_page: number }) =>
  listEmployees({ ...params, status: 'active' })

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
const emptyWarehouse = computed<AppSelectOption>(() => ({
  value: '',
  label: t('assets.filters.allWarehouses'),
}))
const emptyEmployee = computed<AppSelectOption>(() => ({
  value: '',
  label: t('assets.filters.allEmployees'),
}))
const orgUnitFilterOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('assets.filters.allOrgUnits') },
  ...(orgUnitsData.value?.data ?? []).map((u) => ({ value: u.id, label: u.name, hint: u.code })),
])
const orgUnitFormOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('assets.noOrgUnit') },
  ...(orgUnitsData.value?.data ?? []).map((u) => ({ value: u.id, label: u.name, hint: u.code })),
])

const activeFilterCount = computed(() => {
  let count = 0
  if (filters.status !== '') count += 1
  if (filters.category_id !== '') count += 1
  if (filters.warehouse_id !== '') count += 1
  if (filters.organization_unit_id !== '') count += 1
  if (filters.employee_id !== '') count += 1
  return count
})

function resetFilters(): void {
  filters.status = ''
  filters.category_id = ''
  filters.warehouse_id = ''
  filters.organization_unit_id = ''
  filters.employee_id = ''
  filters.search = ''
}

watch(
  () => [
    committedSearch.value,
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
    <AppPageHeader
      :title="t('assets.list.title')"
      :subtitle="t('assets.list.subtitle')"
      :meta="meta ? String(meta.total) : undefined"
    >
      <template #actions>
        <PermissionGuard v-if="showCategories" permission="assets.update">
          <button
            type="button"
            class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl border border-brand-border bg-brand-surface px-4 text-sm font-semibold text-brand-text transition hover:bg-brand-bg sm:w-auto"
            @click="categoriesOpen = true"
          >
            <FolderTree class="h-4 w-4" />
            <span>{{ t('assets.categories.nav') }}</span>
          </button>
        </PermissionGuard>
        <PermissionGuard v-if="showCreate" permission="assets.create">
          <button
            type="button"
            class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white transition hover:bg-brand-primary sm:w-auto"
            @click="openCreate"
          >
            <Plus class="h-4 w-4" />
            <span>{{ t('assets.list.createCta') }}</span>
          </button>
        </PermissionGuard>
      </template>
    </AppPageHeader>

    <AppMobileFilters
      v-model:search="filters.search"
      :search-placeholder="t('assets.list.searchPlaceholder')"
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
              :placeholder="t('assets.list.searchPlaceholder')"
            />
          </div>
          <AppSelect v-model="filters.status" :options="statusOptions" />
          <AppSelect v-model="filters.category_id" :options="categoryFilterOptions" searchable />
          <AppRemoteSelect
            :model-value="filters.warehouse_id"
            query-key="warehouses-active"
            :fetcher="fetchActiveWarehouses"
            :map-option="warehouseSelectOption"
            :empty-option="emptyWarehouse"
            @update:model-value="filters.warehouse_id = toSelectId($event)"
          />
          <AppSelect v-model="filters.organization_unit_id" :options="orgUnitFilterOptions" searchable />
          <AppRemoteSelect
            :model-value="filters.employee_id"
            query-key="employees-active"
            :fetcher="fetchActiveEmployees"
            :map-option="employeeSelectOption"
            :empty-option="emptyEmployee"
            @update:model-value="filters.employee_id = toSelectId($event)"
          />
        </div>
      </template>
      <template #filters>
        <div class="space-y-3">
          <AppSelect v-model="filters.status" :options="statusOptions" />
          <AppSelect v-model="filters.category_id" :options="categoryFilterOptions" searchable />
          <AppRemoteSelect
            :model-value="filters.warehouse_id"
            query-key="warehouses-active"
            :fetcher="fetchActiveWarehouses"
            :map-option="warehouseSelectOption"
            :empty-option="emptyWarehouse"
            @update:model-value="filters.warehouse_id = toSelectId($event)"
          />
          <AppSelect v-model="filters.organization_unit_id" :options="orgUnitFilterOptions" searchable />
          <AppRemoteSelect
            :model-value="filters.employee_id"
            query-key="employees-active"
            :fetcher="fetchActiveEmployees"
            :map-option="employeeSelectOption"
            :empty-option="emptyEmployee"
            @update:model-value="filters.employee_id = toSelectId($event)"
          />
        </div>
      </template>
    </AppMobileFilters>

    <div
      v-if="listState === 'loading'"
      class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center text-sm text-brand-text-muted"
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
      class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center text-sm text-brand-text-muted"
    >
      {{ t('assets.list.empty') }}
    </div>
    <template v-else>
      <div class="hidden overflow-hidden rounded-2xl border border-brand-border bg-brand-surface shadow-[0_1px_2px_rgba(23,32,29,0.03)] lg:block">
        <div class="overflow-x-auto">
        <table class="min-w-full border-separate border-spacing-0 text-sm">
          <thead>
            <tr class="bg-[#F4F6F5]">
              <th class="whitespace-nowrap border-b border-s-[3px] border-brand-border border-s-transparent px-5 py-3.5 text-start text-xs font-bold tracking-wide text-brand-text">
                {{ t('assets.columns.number') }}
              </th>
              <th class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-start text-xs font-bold tracking-wide text-brand-text">
                {{ t('assets.columns.name') }}
              </th>
              <th class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-start text-xs font-bold tracking-wide text-brand-text">
                {{ t('assets.columns.category') }}
              </th>
              <th class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-start text-xs font-bold tracking-wide text-brand-text">
                {{ t('assets.columns.serial') }}
              </th>
              <th class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-start text-xs font-bold tracking-wide text-brand-text">
                {{ t('assets.columns.status') }}
              </th>
              <th class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-start text-xs font-bold tracking-wide text-brand-text">
                {{ t('assets.columns.warehouse') }}
              </th>
              <th class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-start text-xs font-bold tracking-wide text-brand-text">
                {{ t('assets.columns.employee') }}
              </th>
              <th class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-start text-xs font-bold tracking-wide text-brand-text">
                {{ t('assets.columns.orgUnit') }}
              </th>
              <th class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-start text-xs font-bold tracking-wide text-brand-text">
                {{ t('assets.columns.actions') }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="(asset, index) in assets"
              :key="asset.id"
              class="group cursor-pointer"
              :class="index % 2 === 1 ? 'bg-[#FAFBFA]' : 'bg-brand-surface'"
              @click="router.push(`/app/assets/${asset.id}`)"
            >
              <td class="whitespace-nowrap border-b border-s-[3px] border-brand-border/80 border-s-transparent px-5 py-3.5 transition-colors duration-150 group-hover:border-s-brand-primary group-hover:bg-[#EDF6F1]">
                <RouterLink
                  :to="`/app/assets/${asset.id}`"
                  class="inline-flex items-center rounded-lg border border-brand-border bg-brand-bg px-2.5 py-1 font-mono text-[12px] font-bold tracking-wide text-brand-primary-dark shadow-[0_1px_0_rgba(23,32,29,0.04)] transition group-hover:border-brand-primary/30 group-hover:bg-brand-surface hover:border-brand-primary/35 hover:bg-brand-primary-soft hover:text-brand-primary"
                  dir="ltr"
                  @click.stop
                >{{ asset.asset_number }}</RouterLink>
              </td>
              <td class="border-b border-brand-border/80 px-5 py-3.5 font-semibold text-brand-text transition-colors duration-150 group-hover:bg-[#EDF6F1]">{{ asset.name }}</td>
              <td class="border-b border-brand-border/80 px-5 py-3.5 text-brand-text transition-colors duration-150 group-hover:bg-[#EDF6F1]">{{ asset.category?.name || '—' }}</td>
              <td class="border-b border-brand-border/80 px-5 py-3.5 text-brand-text transition-colors duration-150 group-hover:bg-[#EDF6F1]">{{ asset.serial_number || '—' }}</td>
              <td class="border-b border-brand-border/80 px-5 py-3.5 transition-colors duration-150 group-hover:bg-[#EDF6F1]">
                <span
                  class="inline-flex min-w-[6.75rem] items-center justify-center gap-1.5 rounded-full px-3 py-1 text-[11px] font-bold tracking-wide"
                  :class="assetStatusBadgeClass(asset.status)"
                >
                  <span class="h-1.5 w-1.5 shrink-0 rounded-full" :class="assetStatusDotClass(asset.status)" />
                  {{ t(`assets.status.${asset.status}`) }}
                </span>
              </td>
              <td class="border-b border-brand-border/80 px-5 py-3.5 text-brand-text transition-colors duration-150 group-hover:bg-[#EDF6F1]">{{ asset.warehouse?.name || '—' }}</td>
              <td class="border-b border-brand-border/80 px-5 py-3.5 text-brand-text transition-colors duration-150 group-hover:bg-[#EDF6F1]">{{ asset.current_custody?.employee?.full_name || '—' }}</td>
              <td class="border-b border-brand-border/80 px-5 py-3.5 text-brand-text transition-colors duration-150 group-hover:bg-[#EDF6F1]">{{ asset.organization_unit?.name || '—' }}</td>
              <td class="border-b border-brand-border/80 px-5 py-3.5 transition-colors duration-150 group-hover:bg-[#EDF6F1]" @click.stop>
                <div class="inline-flex items-center justify-center gap-0.5 opacity-70 transition group-hover:opacity-100">
                  <PermissionGuard v-if="canEditAsset(permissions)" permission="assets.update">
                    <AppTooltip :text="t('assets.actions.edit')">
                      <button
                        type="button"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-brand-primary-dark transition hover:bg-brand-surface"
                        @click="openEdit(asset)"
                      >
                        <Pencil class="h-4 w-4" />
                      </button>
                    </AppTooltip>
                  </PermissionGuard>
                  <PermissionGuard v-if="canDeleteAsset(permissions)" permission="assets.delete">
                    <AppTooltip :text="t('assets.actions.delete')">
                      <button
                        type="button"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-red-700 transition hover:bg-red-50"
                        @click="removeAsset(asset)"
                      >
                        <Trash2 class="h-4 w-4" />
                      </button>
                    </AppTooltip>
                  </PermissionGuard>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
        </div>
      </div>

      <div class="space-y-3 lg:hidden">
        <article
          v-for="asset in assets"
          :key="asset.id"
          class="rounded-2xl border border-brand-border bg-brand-surface p-4 shadow-[0_1px_2px_rgba(23,32,29,0.03)] transition active:bg-brand-bg"
          @click="router.push(`/app/assets/${asset.id}`)"
        >
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <p class="font-mono text-xs font-bold text-brand-primary-dark" dir="ltr">
                {{ asset.asset_number }}
              </p>
              <h3 class="mt-1 truncate font-bold text-brand-text">{{ asset.name }}</h3>
            </div>
            <span
              class="inline-flex shrink-0 items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-bold"
              :class="assetStatusBadgeClass(asset.status)"
            >
              <span class="h-1.5 w-1.5 shrink-0 rounded-full" :class="assetStatusDotClass(asset.status)" />
              {{ t(`assets.status.${asset.status}`) }}
            </span>
          </div>
          <dl class="mt-3 grid grid-cols-2 gap-2 text-xs text-brand-text-secondary">
            <div>
              <dt>{{ t('assets.columns.warehouse') }}</dt>
              <dd class="mt-0.5 font-medium text-brand-text">{{ asset.warehouse?.name || '—' }}</dd>
            </div>
            <div>
              <dt>{{ t('assets.columns.employee') }}</dt>
              <dd class="mt-0.5 font-medium text-brand-text">
                {{ asset.current_custody?.employee?.full_name || '—' }}
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
          <span>{{ t('assets.prev') }}</span>
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
          <span>{{ t('assets.next') }}</span>
          <ChevronLeft class="h-4 w-4" :stroke-width="2" />
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
      :org-unit-options="orgUnitFormOptions"
      @close="drawerOpen = false"
      @submit="submitForm"
      @update:form="Object.assign(form, $event)"
    />

    <AssetCategoriesManagerDrawer :open="categoriesOpen" @close="categoriesOpen = false" />
  </div>
</template>
