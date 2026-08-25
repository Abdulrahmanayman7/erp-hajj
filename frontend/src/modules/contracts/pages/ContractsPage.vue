<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRouter } from 'vue-router'
import {
  ChevronLeft,
  ChevronRight,
  Eye,
  FolderTree,
  Pencil,
  Plus,
  Search,
} from 'lucide-vue-next'

import { listEmployees } from '@/modules/employees/api/employeesApi'
import { useOrganizationUnitsFlatQuery } from '@/modules/organization/queries/useOrganizationUnitsQuery'
import { ApiError } from '@/shared/api/http'
import AppMobileFilters from '@/shared/components/AppMobileFilters.vue'
import AppPageHeader from '@/shared/components/AppPageHeader.vue'
import AppRemoteSelect from '@/shared/components/AppRemoteSelect.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import { employeeSelectOption, namedCodeOption, toSelectId } from '@/shared/lookups/selectOptions'
import AppTooltip from '@/shared/components/AppTooltip.vue'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { useToast } from '@/shared/composables/useToast'
import { useDebouncedRef } from '@/shared/composables/useDebouncedRef'

import CategoriesManagerDrawer from '../components/CategoriesManagerDrawer.vue'
import ContractFormDrawer from '../components/ContractFormDrawer.vue'
import { useCreateContractMutation, useUpdateContractMutation } from '../mutations/useContractMutations'
import { listCategories } from '../api/categoriesApi'
import { useContractsQuery } from '../queries/useContractsQuery'
import type { Contract, ContractFormState, ContractStatus } from '../types/contracts'
import {
  CONTRACT_STATUSES,
  contractStatusBadgeClass,
  contractStatusDotClass,
  mapContractErrorCode,
  resolveContractsListState,
  validateContractForm,
} from '../validation/contractValidation'

const { t } = useI18n()
const router = useRouter()
const toast = useToast()

const focusedRowIndex = ref(-1)

const filters = reactive({
  search: '',
  status: 'all' as ContractStatus | 'all',
  category_id: '' as number | '',
  organization_unit_id: '' as number | '',
  employee_id: '' as number | '',
  expiring_soon: false,
  page: 1,
  per_page: 15,
  sort: 'created_at',
  direction: 'desc',
})

const committedSearch = useDebouncedRef(() => filters.search)
const queryParams = computed(() => ({
  search: committedSearch.value || undefined,
  status: filters.status === 'all' ? undefined : filters.status,
  category_id: filters.category_id,
  organization_unit_id: filters.organization_unit_id,
  employee_id: filters.employee_id,
  expiring_soon: filters.expiring_soon ? (1 as const) : undefined,
  page: filters.page,
  per_page: filters.per_page,
  sort: filters.sort,
  direction: filters.direction,
}))

const { data, isLoading, isError, refetch, isFetching } = useContractsQuery(queryParams)

const { data: orgUnitsData } = useOrganizationUnitsFlatQuery({ status: 'active' })
const fetchActiveEmployees = (params: { search?: string; page: number; per_page: number }) =>
  listEmployees({ ...params, status: 'active' })
const fetchCategories = (params: { search?: string; page: number; per_page: number }) =>
  listCategories(params)

const createMutation = useCreateContractMutation()
const updateMutation = useUpdateContractMutation()

const contracts = computed(() => data.value?.data ?? [])
const meta = computed(() => data.value?.meta)
const listState = computed(() =>
  resolveContractsListState({
    isLoading: isLoading.value,
    isError: isError.value,
    count: contracts.value.length,
  }),
)

watch(
  contracts,
  (rows) => {
    if (rows.length === 0) {
      focusedRowIndex.value = -1
      return
    }
    if (focusedRowIndex.value >= rows.length) {
      focusedRowIndex.value = rows.length - 1
    }
  },
  { deep: false },
)

function focusRow(index: number): void {
  if (contracts.value.length === 0) {
    focusedRowIndex.value = -1
    return
  }
  focusedRowIndex.value = Math.min(Math.max(index, 0), contracts.value.length - 1)
}

function openFocusedContract(): void {
  const contract = contracts.value[focusedRowIndex.value]
  if (!contract) return
  void router.push(`/app/contracts/${contract.id}`)
}

function onTableKeydown(event: KeyboardEvent): void {
  if (drawerOpen.value || categoriesOpen.value || contracts.value.length === 0) return

  const target = event.target as HTMLElement | null
  if (target) {
    const tag = target.tagName
    if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || target.isContentEditable) {
      return
    }
  }

  if (event.key === 'ArrowDown') {
    event.preventDefault()
    focusRow(focusedRowIndex.value < 0 ? 0 : focusedRowIndex.value + 1)
    return
  }

  if (event.key === 'ArrowUp') {
    event.preventDefault()
    focusRow(focusedRowIndex.value < 0 ? 0 : focusedRowIndex.value - 1)
    return
  }

  if (event.key === 'Home') {
    event.preventDefault()
    focusRow(0)
    return
  }

  if (event.key === 'End') {
    event.preventDefault()
    focusRow(contracts.value.length - 1)
    return
  }

  if (event.key === 'Enter' && focusedRowIndex.value >= 0) {
    event.preventDefault()
    openFocusedContract()
  }
}

function rowToneClass(index: number): string {
  if (focusedRowIndex.value === index) {
    return 'bg-[#EDF6F1]'
  }
  return index % 2 === 1 ? 'bg-[#FAFBFA]' : 'bg-brand-surface'
}

function rowAccentClass(index: number): string {
  return focusedRowIndex.value === index
    ? 'border-s-brand-primary'
    : 'border-s-transparent'
}

const drawerOpen = ref(false)
const editing = ref<Contract | null>(null)
const formError = ref('')
const fieldErrors = reactive<Record<string, string>>({})
const form = reactive<ContractFormState>({
  title: '',
  contract_category_id: '',
  counterparty_name: '',
  counterparty_kind: 'organization',
  employee_id: '',
  organization_unit_id: '',
  start_date: '',
  end_date: '',
  value: '',
  currency: 'SAR',
  notes: '',
})

const categoriesOpen = ref(false)

const statusOptions = computed<AppSelectOption[]>(() => [
  { value: 'all', label: t('contracts.filters.allStatuses') },
  ...CONTRACT_STATUSES.map((status) => ({
    value: status,
    label: t(`contracts.status.${status}`),
  })),
])

const emptyCategory = computed<AppSelectOption>(() => ({
  value: '',
  label: t('contracts.filters.allCategories'),
}))
const emptyEmployee = computed<AppSelectOption>(() => ({
  value: '',
  label: t('contracts.filters.allEmployees'),
}))

const orgUnitFilterOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('contracts.filters.allOrgUnits') },
  ...(orgUnitsData.value?.data ?? []).map((u) => ({
    value: u.id,
    label: u.name,
    hint: u.code,
  })),
])

const orgUnitFormOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('contracts.noOrgUnit') },
  ...(orgUnitsData.value?.data ?? []).map((u) => ({
    value: u.id,
    label: u.name,
    hint: u.code,
  })),
])

const counterpartyKindOptions = computed<AppSelectOption[]>(() => [
  { value: 'organization', label: t('contracts.counterpartyKind.organization') },
  { value: 'person', label: t('contracts.counterpartyKind.person') },
  { value: 'other', label: t('contracts.counterpartyKind.other') },
])

const isFormSubmitting = computed(
  () => createMutation.isPending.value || updateMutation.isPending.value,
)

const activeFilterCount = computed(() => {
  let count = 0
  if (filters.status !== 'all') count += 1
  if (filters.category_id !== '') count += 1
  if (filters.organization_unit_id !== '') count += 1
  if (filters.employee_id !== '') count += 1
  if (filters.expiring_soon) count += 1
  return count
})

function resetFilters(): void {
  filters.status = 'all'
  filters.category_id = ''
  filters.organization_unit_id = ''
  filters.employee_id = ''
  filters.expiring_soon = false
}

watch(
  () => [
    committedSearch.value,
    filters.status,
    filters.category_id,
    filters.organization_unit_id,
    filters.employee_id,
    filters.expiring_soon,
  ],
  () => {
    filters.page = 1
  },
)

function emptyForm(): ContractFormState {
  return {
    title: '',
    contract_category_id: '',
    counterparty_name: '',
    counterparty_kind: 'organization',
    employee_id: '',
    organization_unit_id: '',
    start_date: '',
    end_date: '',
    value: '',
    currency: 'SAR',
    notes: '',
  }
}

function assignForm(next: ContractFormState): void {
  Object.assign(form, next)
}

function clearFieldErrors(): void {
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
}

function fieldMessage(key: string | undefined): string {
  if (!key) return ''
  return t(`contracts.validation.${key}`)
}

function apiMessage(error: unknown): string {
  if (!(error instanceof ApiError)) {
    return t('contracts.errors.generic')
  }
  const mapped = mapContractErrorCode(error.code)
  if (mapped !== 'generic') {
    return t(`contracts.errors.${mapped}`)
  }
  return error.message || t('contracts.errors.generic')
}

function openCreate(): void {
  editing.value = null
  assignForm(emptyForm())
  formError.value = ''
  clearFieldErrors()
  drawerOpen.value = true
}

function openEdit(contract: Contract): void {
  if (contract.status !== 'draft') return
  editing.value = contract
  assignForm({
    title: contract.title,
    contract_category_id: contract.category?.id ?? '',
    counterparty_name: contract.counterparty_name,
    counterparty_kind: contract.counterparty_kind,
    employee_id: contract.employee?.id ?? '',
    organization_unit_id: contract.organization_unit?.id ?? '',
    start_date: contract.start_date,
    end_date: contract.end_date ?? '',
    value: contract.value ?? '',
    currency: contract.currency || 'SAR',
    notes: contract.notes ?? '',
  })
  formError.value = ''
  clearFieldErrors()
  drawerOpen.value = true
}

function closeDrawer(): void {
  drawerOpen.value = false
}

async function submitForm(): Promise<void> {
  formError.value = ''
  clearFieldErrors()
  const validation = validateContractForm(form)
  if (
    validation.title ||
    validation.contract_category_id ||
    validation.counterparty_name ||
    validation.start_date ||
    validation.end_date ||
    validation.value
  ) {
    if (validation.title) fieldErrors.title = fieldMessage(validation.title)
    if (validation.contract_category_id) {
      fieldErrors.contract_category_id = fieldMessage(validation.contract_category_id)
    }
    if (validation.counterparty_name) {
      fieldErrors.counterparty_name = fieldMessage(validation.counterparty_name)
    }
    if (validation.start_date) fieldErrors.start_date = fieldMessage(validation.start_date)
    if (validation.end_date) fieldErrors.end_date = fieldMessage(validation.end_date)
    if (validation.value) fieldErrors.value = fieldMessage(validation.value)
    return
  }

  const payload = {
    title: form.title.trim(),
    contract_category_id: Number(form.contract_category_id),
    counterparty_name: form.counterparty_name.trim(),
    counterparty_kind: form.counterparty_kind,
    employee_id: form.employee_id === '' ? null : Number(form.employee_id),
    organization_unit_id:
      form.organization_unit_id === '' ? null : Number(form.organization_unit_id),
    start_date: form.start_date,
    end_date: form.end_date || null,
    value: form.value.trim() === '' ? null : form.value.trim(),
    currency: form.currency || 'SAR',
    notes: form.notes.trim() || null,
  }

  try {
    if (editing.value) {
      await updateMutation.mutateAsync({ id: editing.value.id, payload })
      toast.success(t('contracts.toasts.updated'))
    } else {
      await createMutation.mutateAsync(payload)
      toast.success(t('contracts.toasts.created'))
    }
    closeDrawer()
  } catch (error) {
    formError.value = apiMessage(error)
  }
}

function formatValue(contract: Contract): string {
  if (contract.value == null || contract.value === '') return '—'
  return `${contract.value} ${contract.currency}`
}

function isCategoryInactive(contract: Contract): boolean {
  return contract.category?.is_active === false
}
</script>

<template>
  <div class="space-y-6">
    <AppPageHeader
      :title="t('contracts.title')"
      :subtitle="t('contracts.subtitle')"
      :meta="meta ? t('contracts.total', { count: meta.total }) : undefined"
    >
      <template #actions>
        <PermissionGuard permission="contracts.update">
          <button
            type="button"
            class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl border border-brand-border bg-brand-surface px-4 text-sm font-semibold text-brand-text transition hover:bg-brand-bg sm:w-auto"
            @click="categoriesOpen = true"
          >
            <FolderTree class="h-4 w-4" :stroke-width="2" />
            <span>{{ t('contracts.categoriesLink') }}</span>
          </button>
        </PermissionGuard>
        <PermissionGuard permission="contracts.create">
          <button
            type="button"
            class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white transition hover:bg-brand-primary sm:w-auto"
            @click="openCreate"
          >
            <Plus class="h-4 w-4" :stroke-width="2.25" />
            <span>{{ t('contracts.add') }}</span>
          </button>
        </PermissionGuard>
      </template>
    </AppPageHeader>

    <AppMobileFilters
      v-model:search="filters.search"
      :search-placeholder="t('contracts.searchPlaceholder')"
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
              :placeholder="t('contracts.searchPlaceholder')"
            />
          </div>
          <AppSelect v-model="filters.status" :options="statusOptions" />
          <AppRemoteSelect
            :model-value="filters.category_id"
            query-key="contract-categories"
            :fetcher="fetchCategories"
            :map-option="namedCodeOption"
            :empty-option="emptyCategory"
            @update:model-value="filters.category_id = toSelectId($event)"
          />
          <AppSelect
            v-model="filters.organization_unit_id"
            :options="orgUnitFilterOptions"
            searchable
          />
          <AppRemoteSelect
            :model-value="filters.employee_id"
            query-key="employees-active"
            :fetcher="fetchActiveEmployees"
            :map-option="employeeSelectOption"
            :empty-option="emptyEmployee"
            @update:model-value="filters.employee_id = toSelectId($event)"
          />
          <label
            class="inline-flex h-11 cursor-pointer items-center gap-2 rounded-xl border border-brand-border bg-brand-surface px-3 text-sm font-semibold text-brand-text"
          >
            <input v-model="filters.expiring_soon" type="checkbox" class="rounded border-brand-border" />
            <span>{{ t('contracts.filters.expiringSoon') }}</span>
          </label>
        </div>
      </template>
      <template #filters>
        <div class="space-y-3">
          <AppSelect v-model="filters.status" :options="statusOptions" />
          <AppRemoteSelect
            :model-value="filters.category_id"
            query-key="contract-categories"
            :fetcher="fetchCategories"
            :map-option="namedCodeOption"
            :empty-option="emptyCategory"
            @update:model-value="filters.category_id = toSelectId($event)"
          />
          <AppSelect
            v-model="filters.organization_unit_id"
            :options="orgUnitFilterOptions"
            searchable
          />
          <AppRemoteSelect
            :model-value="filters.employee_id"
            query-key="employees-active"
            :fetcher="fetchActiveEmployees"
            :map-option="employeeSelectOption"
            :empty-option="emptyEmployee"
            @update:model-value="filters.employee_id = toSelectId($event)"
          />
          <label
            class="inline-flex h-11 w-full cursor-pointer items-center gap-2 rounded-xl border border-brand-border bg-brand-surface px-3 text-sm font-semibold text-brand-text"
          >
            <input v-model="filters.expiring_soon" type="checkbox" class="rounded border-brand-border" />
            <span>{{ t('contracts.filters.expiringSoon') }}</span>
          </label>
        </div>
      </template>
    </AppMobileFilters>

    <div
      v-if="listState === 'loading'"
      class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center text-sm text-brand-text-muted"
    >
      {{ t('contracts.loading') }}
    </div>
    <div
      v-else-if="listState === 'error'"
      class="rounded-2xl border border-red-200 bg-red-50 p-10 text-center"
    >
      <p class="text-sm text-red-700">{{ t('contracts.errors.load') }}</p>
      <button
        type="button"
        class="mt-3 text-sm font-semibold text-brand-primary-dark underline"
        @click="() => refetch()"
      >
        {{ t('contracts.retry') }}
      </button>
    </div>
    <div
      v-else-if="listState === 'empty'"
      class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center"
    >
      <p class="text-sm text-brand-text-muted">{{ t('contracts.empty') }}</p>
      <PermissionGuard permission="contracts.create">
        <button
          type="button"
          class="mt-4 inline-flex h-10 items-center gap-2 rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white"
          @click="openCreate"
        >
          <Plus class="h-4 w-4" />
          {{ t('contracts.add') }}
        </button>
      </PermissionGuard>
    </div>
    <template v-else>
      <div
        ref="tableRoot"
        class="hidden overflow-hidden rounded-2xl border border-brand-border bg-brand-surface shadow-[0_1px_2px_rgba(23,32,29,0.03)] outline-none focus-visible:ring-2 focus-visible:ring-brand-primary/25 md:block"
        tabindex="0"
        role="grid"
        :aria-rowcount="contracts.length"
        :aria-label="t('contracts.title')"
        @keydown="onTableKeydown"
      >
      <div class="overflow-x-auto">
        <table class="min-w-full border-separate border-spacing-0 text-sm">
          <thead>
            <tr class="bg-[#F4F6F5]">
              <th
                class="whitespace-nowrap border-b border-s-[3px] border-brand-border border-s-transparent px-5 py-3.5 text-start text-xs font-bold tracking-wide text-brand-text"
              >
                {{ t('contracts.columns.contractNumber') }}
              </th>
              <th
                class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-start text-xs font-bold tracking-wide text-brand-text"
              >
                {{ t('contracts.columns.title') }}
              </th>
              <th
                class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-center text-xs font-bold tracking-wide text-brand-text"
              >
                {{ t('contracts.columns.category') }}
              </th>
              <th
                class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-center text-xs font-bold tracking-wide text-brand-text"
              >
                {{ t('contracts.columns.counterparty') }}
              </th>
              <th
                class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-center text-xs font-bold tracking-wide text-brand-text"
              >
                {{ t('contracts.columns.startDate') }}
              </th>
              <th
                class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-center text-xs font-bold tracking-wide text-brand-text"
              >
                {{ t('contracts.columns.endDate') }}
              </th>
              <th
                class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-center text-xs font-bold tracking-wide text-brand-text"
              >
                {{ t('contracts.columns.status') }}
              </th>
              <th
                class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-center text-xs font-bold tracking-wide text-brand-text"
              >
                {{ t('contracts.columns.value') }}
              </th>
              <th
                class="w-28 whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-center text-xs font-bold tracking-wide text-brand-text"
              >
                {{ t('contracts.columns.actions') }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="(contract, index) in contracts"
              :key="contract.id"
              class="group"
              :class="rowToneClass(index)"
              role="row"
              :aria-selected="focusedRowIndex === index"
              @mouseenter="focusedRowIndex = index"
            >
              <td
                class="whitespace-nowrap border-b border-brand-border/80 border-s-[3px] px-5 py-3.5 transition-colors duration-150 group-hover:border-s-brand-primary group-hover:bg-[#EDF6F1]"
                :class="rowAccentClass(index)"
              >
                <RouterLink
                  :to="`/app/contracts/${contract.id}`"
                  class="inline-flex items-center gap-1.5 rounded-lg border border-brand-border bg-brand-bg px-2.5 py-1 font-mono text-[12px] font-bold tracking-wide text-brand-primary-dark shadow-[0_1px_0_rgba(23,32,29,0.04)] transition group-hover:border-brand-primary/30 group-hover:bg-brand-surface hover:border-brand-primary/35 hover:bg-brand-primary-soft hover:text-brand-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-primary/25"
                  :title="t('contracts.actions.view')"
                  dir="ltr"
                >
                  {{ contract.contract_number }}
                </RouterLink>
              </td>
              <td class="border-b border-brand-border/80 px-5 py-3.5 font-semibold text-brand-text transition-colors duration-150 group-hover:bg-[#EDF6F1]">
                <RouterLink
                  :to="`/app/contracts/${contract.id}`"
                  class="transition group-hover:text-brand-primary-dark hover:underline hover:underline-offset-2"
                >
                  {{ contract.title }}
                </RouterLink>
              </td>
              <td class="border-b border-brand-border/80 px-5 py-3.5 text-center text-brand-text transition-colors duration-150 group-hover:bg-[#EDF6F1]">
                <span>{{ contract.category?.name ?? '—' }}</span>
                <span
                  v-if="isCategoryInactive(contract)"
                  class="ms-1 inline-flex rounded-full bg-neutral-100 px-1.5 py-0.5 text-[10px] font-semibold text-neutral-600"
                >
                  {{ t('contracts.categoryInactive') }}
                </span>
              </td>
              <td class="border-b border-brand-border/80 px-5 py-3.5 text-center text-brand-text transition-colors duration-150 group-hover:bg-[#EDF6F1]">
                {{ contract.counterparty_name }}
              </td>
              <td
                class="border-b border-brand-border/80 px-5 py-3.5 text-center text-brand-text transition-colors duration-150 group-hover:bg-[#EDF6F1]"
                dir="ltr"
              >
                {{ contract.start_date }}
              </td>
              <td
                class="border-b border-brand-border/80 px-5 py-3.5 text-center text-brand-text transition-colors duration-150 group-hover:bg-[#EDF6F1]"
                dir="ltr"
              >
                {{ contract.end_date ?? '—' }}
              </td>
              <td class="border-b border-brand-border/80 px-5 py-3.5 text-center transition-colors duration-150 group-hover:bg-[#EDF6F1]">
                <div class="flex flex-wrap items-center justify-center gap-1.5">
                  <span
                    class="inline-flex min-w-[7.25rem] items-center justify-center gap-1.5 rounded-full px-3 py-1 text-[11px] font-bold tracking-wide shadow-sm"
                    :class="contractStatusBadgeClass(contract.status)"
                  >
                    <span
                      class="h-1.5 w-1.5 shrink-0 rounded-full"
                      :class="contractStatusDotClass(contract.status)"
                      aria-hidden="true"
                    />
                    {{ t(`contracts.status.${contract.status}`) }}
                  </span>
                  <span
                    v-if="contract.is_expiring_soon"
                    class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-900 ring-1 ring-inset ring-amber-200/80"
                  >
                    {{ t('contracts.expiringSoonBadge') }}
                  </span>
                </div>
              </td>
              <td
                class="border-b border-brand-border/80 px-5 py-3.5 text-center text-brand-text transition-colors duration-150 group-hover:bg-[#EDF6F1]"
                dir="ltr"
              >
                {{ formatValue(contract) }}
              </td>
              <td class="border-b border-brand-border/80 px-5 py-3.5 text-center transition-colors duration-150 group-hover:bg-[#EDF6F1]">
                <div class="inline-flex items-center justify-center gap-0.5 opacity-70 transition group-hover:opacity-100">
                  <AppTooltip :text="t('contracts.actions.view')">
                    <RouterLink
                      :to="`/app/contracts/${contract.id}`"
                      class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-brand-text transition hover:bg-brand-surface hover:text-brand-primary-dark"
                      :aria-label="t('contracts.actions.view')"
                    >
                      <Eye class="h-4 w-4" :stroke-width="2" />
                    </RouterLink>
                  </AppTooltip>
                  <PermissionGuard
                    v-if="contract.status === 'draft'"
                    permission="contracts.update"
                  >
                    <AppTooltip :text="t('contracts.actions.edit')">
                      <button
                        type="button"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-brand-primary-dark transition hover:bg-brand-surface"
                        :aria-label="t('contracts.actions.edit')"
                        @click="openEdit(contract)"
                      >
                        <Pencil class="h-4 w-4" :stroke-width="2" />
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

      <div class="space-y-3 md:hidden">
        <RouterLink
          v-for="contract in contracts"
          :key="contract.id"
          :to="`/app/contracts/${contract.id}`"
          class="block rounded-2xl border border-brand-border bg-brand-surface p-4 shadow-[0_1px_2px_rgba(23,32,29,0.03)] transition active:bg-brand-bg"
        >
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <p class="font-mono text-xs font-bold text-brand-primary-dark" dir="ltr">
                {{ contract.contract_number }}
              </p>
              <h3 class="mt-1 font-bold text-brand-text">{{ contract.title }}</h3>
              <p class="mt-1 text-sm text-brand-text-secondary">{{ contract.counterparty_name }}</p>
            </div>
            <div class="flex shrink-0 flex-col items-end gap-1">
              <span
                class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-bold"
                :class="contractStatusBadgeClass(contract.status)"
              >
                <span
                  class="h-1.5 w-1.5 shrink-0 rounded-full"
                  :class="contractStatusDotClass(contract.status)"
                />
                {{ t(`contracts.status.${contract.status}`) }}
              </span>
              <span
                v-if="contract.is_expiring_soon"
                class="inline-flex rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-900 ring-1 ring-inset ring-amber-200/80"
              >
                {{ t('contracts.expiringSoonBadge') }}
              </span>
            </div>
          </div>
          <dl class="mt-3 grid grid-cols-2 gap-2 text-xs text-brand-text-secondary">
            <div>
              <dt>{{ t('contracts.columns.category') }}</dt>
              <dd class="font-medium text-brand-text">{{ contract.category?.name ?? '—' }}</dd>
            </div>
            <div>
              <dt>{{ t('contracts.columns.value') }}</dt>
              <dd class="font-medium text-brand-text" dir="ltr">{{ formatValue(contract) }}</dd>
            </div>
            <div>
              <dt>{{ t('contracts.columns.startDate') }}</dt>
              <dd class="font-medium text-brand-text" dir="ltr">{{ contract.start_date }}</dd>
            </div>
            <div>
              <dt>{{ t('contracts.columns.endDate') }}</dt>
              <dd class="font-medium text-brand-text" dir="ltr">{{ contract.end_date ?? '—' }}</dd>
            </div>
          </dl>
        </RouterLink>
      </div>

      <div
        v-if="meta && meta.last_page > 1"
        class="flex items-center justify-between gap-3"
      >
        <button
          type="button"
          class="inline-flex h-11 items-center gap-1 rounded-xl border border-brand-border bg-brand-surface px-3 text-sm font-semibold text-brand-text transition hover:bg-brand-bg disabled:cursor-not-allowed disabled:opacity-40"
          :disabled="filters.page <= 1 || isFetching"
          @click="filters.page -= 1"
        >
          <ChevronRight class="h-4 w-4" :stroke-width="2" />
          <span>{{ t('contracts.prev') }}</span>
        </button>
        <span class="text-xs font-semibold text-brand-text-muted">
          {{ filters.page }} / {{ meta.last_page }}
        </span>
        <button
          type="button"
          class="inline-flex h-11 items-center gap-1 rounded-xl border border-brand-border bg-brand-surface px-3 text-sm font-semibold text-brand-text transition hover:bg-brand-bg disabled:cursor-not-allowed disabled:opacity-40"
          :disabled="filters.page >= meta.last_page || isFetching"
          @click="filters.page += 1"
        >
          <span>{{ t('contracts.next') }}</span>
          <ChevronLeft class="h-4 w-4" :stroke-width="2" />
        </button>
      </div>
    </template>

    <ContractFormDrawer
      :open="drawerOpen"
      :editing="editing"
      :form="form"
      :form-error="formError"
      :field-errors="fieldErrors"
      :submitting="isFormSubmitting"
      :org-unit-options="orgUnitFormOptions"
      :counterparty-kind-options="counterpartyKindOptions"
      @close="closeDrawer"
      @submit="submitForm"
      @update:form="assignForm"
    />

    <CategoriesManagerDrawer :open="categoriesOpen" @close="categoriesOpen = false" />
  </div>
</template>
