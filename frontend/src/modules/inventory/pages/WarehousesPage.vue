<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { ChevronLeft, ChevronRight, Eye, Pencil, Plus, Power, PowerOff, Search, Trash2 } from 'lucide-vue-next'

import { listEmployees } from '@/modules/employees/api/employeesApi'
import { useOrganizationUnitsFlatQuery } from '@/modules/organization/queries/useOrganizationUnitsQuery'
import { ApiError } from '@/shared/api/http'
import AppMobileFilters from '@/shared/components/AppMobileFilters.vue'
import AppPageHeader from '@/shared/components/AppPageHeader.vue'
import AppRemoteSelect from '@/shared/components/AppRemoteSelect.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import { employeeSelectOption, toSelectId } from '@/shared/lookups/selectOptions'
import AppTooltip from '@/shared/components/AppTooltip.vue'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { useConfirm } from '@/shared/composables/useConfirm'
import { usePermissions } from '@/shared/composables/usePermissions'
import { useToast } from '@/shared/composables/useToast'
import { useDebouncedRef } from '@/shared/composables/useDebouncedRef'

import WarehouseFormDrawer from '../components/WarehouseFormDrawer.vue'
import {
  useActivateWarehouseMutation,
  useCreateWarehouseMutation,
  useDeactivateWarehouseMutation,
  useDeleteWarehouseMutation,
  useUpdateWarehouseMutation,
} from '../mutations/useWarehouseMutations'
import { useWarehousesQuery } from '../queries/useWarehousesQuery'
import type { ListWarehousesParams, Warehouse, WarehouseFormState } from '../types/warehouses'
import {
  mapInventoryErrorCode,
  resolveInventoryListState,
  validateWarehouseForm,
} from '../validation/inventoryValidation'

const { t } = useI18n()
const router = useRouter()
const { can } = usePermissions()
const toast = useToast()
const { confirm } = useConfirm()

const filters = reactive({
  search: '',
  is_active: '' as '' | '1' | '0',
  organization_unit_id: '' as number | '',
  responsible_employee_id: '' as number | '',
  page: 1,
  per_page: 15,
})

const committedSearch = useDebouncedRef(() => filters.search)
const params = computed<ListWarehousesParams>(() => ({
  search: committedSearch.value || undefined,
  is_active: filters.is_active === '' ? '' : filters.is_active === '1',
  organization_unit_id: filters.organization_unit_id,
  responsible_employee_id: filters.responsible_employee_id,
  page: filters.page,
  per_page: filters.per_page,
}))

const { data, isLoading, isError, refetch } = useWarehousesQuery(params)
const warehouses = computed(() => data.value?.data ?? [])
const meta = computed(() => data.value?.meta)
const listState = computed(() =>
  resolveInventoryListState({
    isLoading: isLoading.value,
    isError: isError.value,
    count: warehouses.value.length,
  }),
)

const { data: orgUnitsData } = useOrganizationUnitsFlatQuery({ status: 'active' })
const fetchActiveEmployees = (params: { search?: string; page: number; per_page: number }) =>
  listEmployees({ ...params, status: 'active' })

const statusOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('inventory.filters.allStatuses') },
  { value: '1', label: t('inventory.status.active') },
  { value: '0', label: t('inventory.status.inactive') },
])

const orgUnitOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('inventory.filters.allOrgUnits') },
  ...(orgUnitsData.value?.data ?? []).map((u) => ({ value: u.id, label: u.name, hint: u.code })),
])

const orgUnitFormOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('inventory.noOrgUnit') },
  ...(orgUnitsData.value?.data ?? []).map((u) => ({ value: u.id, label: u.name, hint: u.code })),
])
const emptyEmployeeFilter = computed<AppSelectOption>(() => ({
  value: '',
  label: t('inventory.filters.allEmployees'),
}))

const activeFilterCount = computed(() => {
  let count = 0
  if (filters.is_active !== '') count += 1
  if (filters.organization_unit_id !== '') count += 1
  if (filters.responsible_employee_id !== '') count += 1
  return count
})

function resetFilters(): void {
  filters.is_active = ''
  filters.organization_unit_id = ''
  filters.responsible_employee_id = ''
  filters.search = ''
}

watch(
  () => [committedSearch.value, filters.is_active, filters.organization_unit_id, filters.responsible_employee_id],
  () => {
    filters.page = 1
  },
)

const createMutation = useCreateWarehouseMutation()
const updateMutation = useUpdateWarehouseMutation()
const activateMutation = useActivateWarehouseMutation()
const deactivateMutation = useDeactivateWarehouseMutation()
const deleteMutation = useDeleteWarehouseMutation()

const drawerOpen = ref(false)
const editing = ref<Warehouse | null>(null)
const formError = ref('')
const fieldErrors = reactive<Record<string, string>>({})
const form = reactive<WarehouseFormState>({
  name: '',
  description: '',
  location: '',
  organization_unit_id: '',
  responsible_employee_id: '',
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
    location: '',
    organization_unit_id: '',
    responsible_employee_id: '',
    is_active: true,
    notes: '',
  })
  formError.value = ''
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  drawerOpen.value = true
}

function openEdit(warehouse: Warehouse): void {
  editing.value = warehouse
  Object.assign(form, {
    name: warehouse.name,
    description: warehouse.description ?? '',
    location: warehouse.location ?? '',
    organization_unit_id: warehouse.organization_unit?.id ?? '',
    responsible_employee_id: warehouse.responsible_employee?.id ?? '',
    is_active: warehouse.is_active,
    notes: warehouse.notes ?? '',
  })
  formError.value = ''
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  drawerOpen.value = true
}

async function submitForm(): Promise<void> {
  formError.value = ''
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  Object.assign(fieldErrors, validateWarehouseForm(form))
  if (Object.keys(fieldErrors).length) return

  const payload = {
    name: form.name.trim(),
    description: form.description.trim() || null,
    location: form.location.trim() || null,
    organization_unit_id: form.organization_unit_id === '' ? null : Number(form.organization_unit_id),
    responsible_employee_id:
      form.responsible_employee_id === '' ? null : Number(form.responsible_employee_id),
    is_active: form.is_active,
    notes: form.notes.trim() || null,
  }

  try {
    if (editing.value) {
      await updateMutation.mutateAsync({ id: editing.value.id, payload })
      toast.success(t('inventory.toasts.warehouseUpdated'))
    } else {
      await createMutation.mutateAsync(payload)
      toast.success(t('inventory.toasts.warehouseCreated'))
    }
    drawerOpen.value = false
  } catch (error) {
    formError.value = apiMessage(error)
  }
}

async function toggleActive(warehouse: Warehouse): Promise<void> {
  if (!can('warehouses.update')) return
  const next = !warehouse.is_active
  const ok = await confirm({
    title: next ? t('inventory.confirm.activateWarehouse.title') : t('inventory.confirm.deactivateWarehouse.title'),
    message: next
      ? t('inventory.confirm.activateWarehouse.body')
      : t('inventory.confirm.deactivateWarehouse.body'),
    confirmLabel: next ? t('inventory.actions.activate') : t('inventory.actions.deactivate'),
    variant: 'warning',
  })
  if (!ok) return
  try {
    if (next) await activateMutation.mutateAsync(warehouse.id)
    else await deactivateMutation.mutateAsync(warehouse.id)
    toast.success(next ? t('inventory.toasts.warehouseActivated') : t('inventory.toasts.warehouseDeactivated'))
  } catch (error) {
    toast.error(apiMessage(error))
  }
}

async function removeWarehouse(warehouse: Warehouse): Promise<void> {
  if (!can('warehouses.delete')) return
  const ok = await confirm({
    title: t('inventory.confirm.deleteWarehouse.title'),
    message: t('inventory.confirm.deleteWarehouse.body'),
    confirmLabel: t('inventory.actions.delete'),
    variant: 'danger',
  })
  if (!ok) return
  try {
    await deleteMutation.mutateAsync(warehouse.id)
    toast.success(t('inventory.toasts.warehouseDeleted'))
  } catch (error) {
    toast.error(apiMessage(error))
  }
}
</script>

<template>
  <div class="space-y-6">
    <AppPageHeader
      :title="t('inventory.warehouses.title')"
      :subtitle="t('inventory.warehouses.subtitle')"
      :meta="meta ? String(meta.total) : undefined"
    >
      <template #actions>
        <PermissionGuard permission="warehouses.create">
          <button
            type="button"
            class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/30 sm:w-auto"
            @click="openCreate"
          >
            <Plus class="h-4 w-4" />
            <span>{{ t('inventory.warehouses.createCta') }}</span>
          </button>
        </PermissionGuard>
      </template>
    </AppPageHeader>

    <AppMobileFilters
      v-model:search="filters.search"
      :search-placeholder="t('inventory.warehouses.searchPlaceholder')"
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
              :placeholder="t('inventory.warehouses.searchPlaceholder')"
            />
          </div>
          <AppSelect v-model="filters.is_active" :options="statusOptions" />
          <AppSelect v-model="filters.organization_unit_id" :options="orgUnitOptions" searchable />
          <AppRemoteSelect
            :model-value="filters.responsible_employee_id"
            query-key="employees-active"
            :fetcher="fetchActiveEmployees"
            :map-option="employeeSelectOption"
            :empty-option="emptyEmployeeFilter"
            @update:model-value="filters.responsible_employee_id = toSelectId($event)"
          />
        </div>
      </template>
      <template #filters>
        <div class="space-y-3">
          <AppSelect v-model="filters.is_active" :options="statusOptions" />
          <AppSelect v-model="filters.organization_unit_id" :options="orgUnitOptions" searchable />
          <AppRemoteSelect
            :model-value="filters.responsible_employee_id"
            query-key="employees-active"
            :fetcher="fetchActiveEmployees"
            :map-option="employeeSelectOption"
            :empty-option="emptyEmployeeFilter"
            @update:model-value="filters.responsible_employee_id = toSelectId($event)"
          />
        </div>
      </template>
    </AppMobileFilters>

    <div v-if="listState === 'loading'" class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center text-sm text-brand-text-muted shadow-sm">
      {{ t('inventory.loading') }}
    </div>
    <div v-else-if="listState === 'error'" class="rounded-2xl border border-red-200 bg-red-50 p-10 text-center">
      <p class="text-sm text-red-700">{{ t('inventory.errors.load') }}</p>
      <button type="button" class="mt-3 text-sm font-semibold underline" @click="() => refetch()">
        {{ t('inventory.retry') }}
      </button>
    </div>
    <div v-else-if="listState === 'empty'" class="rounded-2xl border border-dashed border-brand-border bg-brand-surface p-10 text-center text-sm text-brand-text-muted">
      {{ t('inventory.warehouses.empty') }}
    </div>
    <template v-else>
      <div class="hidden overflow-hidden rounded-2xl border border-brand-border bg-brand-surface shadow-[0_1px_2px_rgba(23,32,29,0.03)] md:block">
        <div class="overflow-x-auto">
          <table class="min-w-full border-separate border-spacing-0 text-sm">
            <thead>
              <tr class="bg-[#F4F6F5]">
                <th
                  v-for="(key, columnIndex) in ['number', 'name', 'location', 'orgUnit', 'responsible', 'status', 'actions']"
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
                v-for="(warehouse, index) in warehouses"
                :key="warehouse.id"
                class="group cursor-pointer"
                :class="index % 2 === 1 ? 'bg-[#FAFBFA]' : 'bg-brand-surface'"
                @click="router.push(`/app/warehouses/${warehouse.id}`)"
              >
                <td
                  class="whitespace-nowrap border-b border-s-[3px] border-brand-border/80 border-s-transparent px-5 py-3.5 text-center transition-colors duration-150 group-hover:border-s-brand-primary group-hover:bg-[#EDF6F1]"
                >
                  <span
                    class="inline-flex items-center rounded-lg border border-brand-border bg-brand-bg px-2.5 py-1 font-mono text-[12px] font-bold tracking-wide text-brand-text shadow-[0_1px_0_rgba(23,32,29,0.04)] transition group-hover:border-brand-primary/30 group-hover:bg-brand-surface"
                    dir="ltr"
                  >
                    {{ warehouse.warehouse_number }}
                  </span>
                </td>
                <td
                  class="max-w-[14rem] whitespace-nowrap border-b border-brand-border/80 px-5 py-3.5 text-center font-semibold text-brand-text transition-colors duration-150 group-hover:bg-[#EDF6F1]"
                >
                  <span class="block truncate" :title="warehouse.name">{{ warehouse.name }}</span>
                </td>
                <td
                  class="max-w-[12rem] whitespace-nowrap border-b border-brand-border/80 px-5 py-3.5 text-center text-brand-text transition-colors duration-150 group-hover:bg-[#EDF6F1]"
                >
                  <span class="block truncate" :title="warehouse.location || undefined">{{ warehouse.location || '—' }}</span>
                </td>
                <td
                  class="max-w-[12rem] whitespace-nowrap border-b border-brand-border/80 px-5 py-3.5 text-center text-brand-text transition-colors duration-150 group-hover:bg-[#EDF6F1]"
                >
                  <span class="block truncate" :title="warehouse.organization_unit?.name || undefined">
                    {{ warehouse.organization_unit?.name || '—' }}
                  </span>
                </td>
                <td
                  class="max-w-[12rem] whitespace-nowrap border-b border-brand-border/80 px-5 py-3.5 text-center text-brand-text transition-colors duration-150 group-hover:bg-[#EDF6F1]"
                >
                  <span class="block truncate" :title="warehouse.responsible_employee?.full_name || undefined">
                    {{ warehouse.responsible_employee?.full_name || '—' }}
                  </span>
                </td>
                <td
                  class="whitespace-nowrap border-b border-brand-border/80 px-5 py-3.5 text-center transition-colors duration-150 group-hover:bg-[#EDF6F1]"
                >
                  <span
                    class="inline-flex min-w-[6.5rem] items-center justify-center gap-1.5 rounded-full px-3 py-1 text-[11px] font-bold tracking-wide text-brand-text shadow-sm ring-1 ring-inset"
                    :class="
                      warehouse.is_active
                        ? 'bg-emerald-100 ring-emerald-300/80'
                        : 'bg-neutral-100 ring-neutral-300/80'
                    "
                  >
                    <span
                      class="h-1.5 w-1.5 shrink-0 rounded-full"
                      :class="warehouse.is_active ? 'bg-emerald-500' : 'bg-neutral-400'"
                      aria-hidden="true"
                    />
                    {{ warehouse.is_active ? t('inventory.status.active') : t('inventory.status.inactive') }}
                  </span>
                </td>
                <td
                  class="whitespace-nowrap border-b border-brand-border/80 px-5 py-3.5 text-center transition-colors duration-150 group-hover:bg-[#EDF6F1]"
                  @click.stop
                >
                  <div
                    class="mx-auto grid w-[4.75rem] grid-cols-2 place-items-center gap-0.5 opacity-70 transition group-hover:opacity-100"
                  >
                    <div>
                      <AppTooltip :text="t('inventory.actions.view')">
                        <button
                          type="button"
                          class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-brand-text transition hover:bg-brand-surface hover:text-brand-primary-dark"
                          :aria-label="t('inventory.actions.view')"
                          @click="router.push(`/app/warehouses/${warehouse.id}`)"
                        >
                          <Eye class="h-4 w-4" :stroke-width="2" />
                        </button>
                      </AppTooltip>
                    </div>
                    <div v-if="can('warehouses.update')">
                      <AppTooltip :text="t('inventory.actions.edit')">
                        <button
                          type="button"
                          class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-brand-text transition hover:bg-brand-surface"
                          :aria-label="t('inventory.actions.edit')"
                          @click="openEdit(warehouse)"
                        >
                          <Pencil class="h-4 w-4" :stroke-width="2" />
                        </button>
                      </AppTooltip>
                    </div>
                    <div v-if="can('warehouses.update')">
                      <AppTooltip
                        :text="
                          warehouse.is_active
                            ? t('inventory.actions.deactivate')
                            : t('inventory.actions.activate')
                        "
                      >
                        <button
                          type="button"
                          class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-brand-text transition hover:bg-brand-surface"
                          :aria-label="
                            warehouse.is_active
                              ? t('inventory.actions.deactivate')
                              : t('inventory.actions.activate')
                          "
                          @click="toggleActive(warehouse)"
                        >
                          <PowerOff v-if="warehouse.is_active" class="h-4 w-4" :stroke-width="2" />
                          <Power v-else class="h-4 w-4" :stroke-width="2" />
                        </button>
                      </AppTooltip>
                    </div>
                    <div v-if="can('warehouses.delete')">
                      <AppTooltip :text="t('inventory.actions.delete')">
                        <button
                          type="button"
                          class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-brand-text transition hover:bg-red-50 hover:text-red-700"
                          :aria-label="t('inventory.actions.delete')"
                          @click="removeWarehouse(warehouse)"
                        >
                          <Trash2 class="h-4 w-4" :stroke-width="2" />
                        </button>
                      </AppTooltip>
                    </div>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="space-y-3 md:hidden">
        <article
          v-for="warehouse in warehouses"
          :key="warehouse.id"
          class="rounded-2xl border border-brand-border bg-brand-surface p-4 shadow-[0_1px_2px_rgba(23,32,29,0.03)]"
        >
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <p class="font-mono text-xs font-bold text-brand-primary-dark" dir="ltr">
                {{ warehouse.warehouse_number }}
              </p>
              <h3 class="mt-1 truncate font-bold text-brand-text">{{ warehouse.name }}</h3>
            </div>
            <span
              class="inline-flex shrink-0 items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-bold text-brand-text ring-1 ring-inset"
              :class="
                warehouse.is_active
                  ? 'bg-emerald-100 ring-emerald-300/80'
                  : 'bg-neutral-100 ring-neutral-300/80'
              "
            >
              <span
                class="h-1.5 w-1.5 rounded-full"
                :class="warehouse.is_active ? 'bg-emerald-500' : 'bg-neutral-400'"
              />
              {{ warehouse.is_active ? t('inventory.status.active') : t('inventory.status.inactive') }}
            </span>
          </div>
          <dl class="mt-3 grid grid-cols-2 gap-2 text-xs text-brand-text-secondary">
            <div class="col-span-2">
              <dt>{{ t('inventory.columns.location') }}</dt>
              <dd class="mt-0.5 font-medium text-brand-text">{{ warehouse.location || '—' }}</dd>
            </div>
          </dl>
          <div class="mt-3 flex items-center justify-end border-t border-brand-border pt-3">
            <button
              type="button"
              class="inline-flex h-11 items-center gap-2 rounded-xl px-3 text-sm font-semibold text-brand-primary-dark transition hover:bg-brand-primary-soft"
              @click="router.push(`/app/warehouses/${warehouse.id}`)"
            >
              <Eye class="h-4 w-4" :stroke-width="2" />
              {{ t('inventory.actions.view') }}
            </button>
          </div>
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

    <WarehouseFormDrawer
      :open="drawerOpen"
      :editing="editing"
      :form="form"
      :form-error="formError"
      :field-errors="fieldErrors"
      :submitting="submitting"
      :org-unit-options="orgUnitFormOptions"
      @close="drawerOpen = false"
      @submit="submitForm"
      @update:form="Object.assign(form, $event)"
    />
  </div>
</template>
