<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import {
  ChevronLeft,
  ChevronRight,
  Eye,
  FolderTree,
  Pencil,
  Plus,
  Search,
} from 'lucide-vue-next'

import { useEmployeesQuery } from '@/modules/employees/queries/useEmployeesQuery'
import { useOrganizationUnitsFlatQuery } from '@/modules/organization/queries/useOrganizationUnitsQuery'
import { ApiError } from '@/shared/api/http'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import AppTooltip from '@/shared/components/AppTooltip.vue'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { usePermissions } from '@/shared/composables/usePermissions'
import { useToast } from '@/shared/composables/useToast'

import CategoriesManagerDrawer from '../components/CategoriesManagerDrawer.vue'
import ContractFormDrawer from '../components/ContractFormDrawer.vue'
import {
  useCreateContractMutation,
  useUpdateContractMutation,
} from '../mutations/useContractMutations'
import { useCategoriesQuery } from '../queries/useCategoriesQuery'
import { useContractsQuery } from '../queries/useContractsQuery'
import type { Contract, ContractFormState, ContractStatus } from '../types/contracts'
import {
  CONTRACT_STATUSES,
  contractStatusBadgeClass,
  mapContractErrorCode,
  resolveContractsListState,
  validateContractForm,
} from '../validation/contractValidation'

const { t } = useI18n()
const { can } = usePermissions()
const toast = useToast()

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

const queryParams = computed(() => ({
  search: filters.search || undefined,
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
const { data: categoriesData } = useCategoriesQuery(
  { per_page: 100 },
  {
    enabled: computed(
      () => can('contracts.view') || can('contracts.create') || can('contracts.update'),
    ),
  },
)
const { data: activeCategoriesData } = useCategoriesQuery(
  { is_active: true, per_page: 100 },
  {
    enabled: computed(() => can('contracts.create') || can('contracts.update')),
  },
)
const { data: employeesData } = useEmployeesQuery(
  computed(() => ({ status: 'active' as const, per_page: 100 })),
)

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

const categoryFilterOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('contracts.filters.allCategories') },
  ...(categoriesData.value?.data ?? []).map((c) => ({
    value: c.id,
    label: c.name,
    hint: c.code ?? undefined,
  })),
])

const categoryFormOptions = computed<AppSelectOption[]>(() => {
  const active = activeCategoriesData.value?.data ?? []
  const options = active.map((c) => ({
    value: c.id,
    label: c.name,
    hint: c.code ?? undefined,
  }))

  // Keep current inactive category selectable while editing a draft.
  if (editing.value?.category) {
    const currentId = editing.value.category.id
    const exists = options.some((o) => o.value === currentId)
    if (!exists) {
      const full = (categoriesData.value?.data ?? []).find((c) => c.id === currentId)
      options.unshift({
        value: currentId,
        label: editing.value.category.name,
        hint: full && !full.is_active ? t('contracts.categoryInactive') : (editing.value.category.code ?? undefined),
      })
    }
  }

  return options
})

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

const employeeFilterOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('contracts.filters.allEmployees') },
  ...(employeesData.value?.data ?? []).map((e) => ({
    value: e.id,
    label: e.full_name,
    hint: e.employee_number,
  })),
])

const employeeFormOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('contracts.noEmployee') },
  ...(employeesData.value?.data ?? []).map((e) => ({
    value: e.id,
    label: e.full_name,
    hint: e.employee_number,
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

watch(
  () => [
    filters.search,
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
  const id = contract.category?.id
  if (id == null) return false
  if (contract.category?.is_active === false) return true
  const found = (categoriesData.value?.data ?? []).find((c) => c.id === id)
  return found ? !found.is_active : false
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
      <div class="min-w-0">
        <h2 class="text-[1.75rem] font-bold leading-tight text-brand-text">
          {{ t('contracts.title') }}
        </h2>
        <p class="mt-1.5 text-sm text-brand-text-secondary">
          {{ t('contracts.subtitle') }}
        </p>
        <p v-if="meta" class="mt-2">
          <span
            class="inline-flex items-center rounded-full bg-brand-primary-soft px-2.5 py-0.5 text-xs font-semibold text-brand-primary-dark"
          >
            {{ t('contracts.total', { count: meta.total }) }}
          </span>
        </p>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <PermissionGuard permission="contracts.update">
          <button
            type="button"
            class="inline-flex h-11 items-center gap-2 rounded-xl border border-brand-border bg-brand-surface px-4 text-sm font-semibold text-brand-text transition hover:bg-brand-bg"
            @click="categoriesOpen = true"
          >
            <FolderTree class="h-4 w-4" :stroke-width="2" />
            <span>{{ t('contracts.categoriesLink') }}</span>
          </button>
        </PermissionGuard>
        <PermissionGuard permission="contracts.create">
          <button
            type="button"
            class="inline-flex h-11 items-center gap-2 rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white transition hover:bg-brand-primary"
            @click="openCreate"
          >
            <Plus class="h-4 w-4" :stroke-width="2.25" />
            <span>{{ t('contracts.add') }}</span>
          </button>
        </PermissionGuard>
      </div>
    </div>

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
      <AppSelect v-model="filters.category_id" :options="categoryFilterOptions" searchable />
      <AppSelect
        v-model="filters.organization_unit_id"
        :options="orgUnitFilterOptions"
        searchable
      />
      <AppSelect v-model="filters.employee_id" :options="employeeFilterOptions" searchable />
      <label
        class="inline-flex h-11 cursor-pointer items-center gap-2 rounded-xl border border-brand-border bg-brand-surface px-3 text-sm font-semibold text-brand-text"
      >
        <input v-model="filters.expiring_soon" type="checkbox" class="rounded border-brand-border" />
        <span>{{ t('contracts.filters.expiringSoon') }}</span>
      </label>
    </div>

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
    <div
      v-else
      class="overflow-hidden rounded-2xl border border-brand-border bg-brand-surface shadow-[0_1px_2px_rgba(23,32,29,0.03)]"
    >
      <div class="overflow-x-auto">
        <table class="min-w-full border-separate border-spacing-0 text-sm">
          <thead>
            <tr class="bg-[#F4F6F5]">
              <th
                class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-start text-xs font-bold tracking-wide text-brand-text-muted"
              >
                {{ t('contracts.columns.contractNumber') }}
              </th>
              <th
                class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-start text-xs font-bold tracking-wide text-brand-text-muted"
              >
                {{ t('contracts.columns.title') }}
              </th>
              <th
                class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-center text-xs font-bold tracking-wide text-brand-text-muted"
              >
                {{ t('contracts.columns.category') }}
              </th>
              <th
                class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-center text-xs font-bold tracking-wide text-brand-text-muted"
              >
                {{ t('contracts.columns.counterparty') }}
              </th>
              <th
                class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-center text-xs font-bold tracking-wide text-brand-text-muted"
              >
                {{ t('contracts.columns.startDate') }}
              </th>
              <th
                class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-center text-xs font-bold tracking-wide text-brand-text-muted"
              >
                {{ t('contracts.columns.endDate') }}
              </th>
              <th
                class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-center text-xs font-bold tracking-wide text-brand-text-muted"
              >
                {{ t('contracts.columns.status') }}
              </th>
              <th
                class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-center text-xs font-bold tracking-wide text-brand-text-muted"
              >
                {{ t('contracts.columns.value') }}
              </th>
              <th
                class="w-28 whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-center text-xs font-bold tracking-wide text-brand-text-muted"
              >
                {{ t('contracts.columns.actions') }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="(contract, index) in contracts"
              :key="contract.id"
              class="group transition-colors duration-150"
              :class="index % 2 === 1 ? 'bg-[#FAFBFA]' : 'bg-brand-surface'"
            >
              <td
                class="whitespace-nowrap border-b border-brand-border/80 px-5 py-3.5 font-mono text-xs text-brand-text-secondary group-hover:bg-[#EEF2F0]"
                dir="ltr"
              >
                <RouterLink
                  :to="`/app/contracts/${contract.id}`"
                  class="font-semibold text-brand-primary-dark hover:underline"
                >
                  {{ contract.contract_number }}
                </RouterLink>
              </td>
              <td
                class="border-b border-brand-border/80 px-5 py-3.5 font-semibold text-brand-text group-hover:bg-[#EEF2F0]"
              >
                <RouterLink
                  :to="`/app/contracts/${contract.id}`"
                  class="hover:text-brand-primary-dark"
                >
                  {{ contract.title }}
                </RouterLink>
              </td>
              <td
                class="border-b border-brand-border/80 px-5 py-3.5 text-center text-brand-text-secondary group-hover:bg-[#EEF2F0]"
              >
                <span>{{ contract.category?.name ?? '—' }}</span>
                <span
                  v-if="isCategoryInactive(contract)"
                  class="ms-1 inline-flex rounded-full bg-neutral-100 px-1.5 py-0.5 text-[10px] font-semibold text-neutral-600"
                >
                  {{ t('contracts.categoryInactive') }}
                </span>
              </td>
              <td
                class="border-b border-brand-border/80 px-5 py-3.5 text-center text-brand-text-secondary group-hover:bg-[#EEF2F0]"
              >
                {{ contract.counterparty_name }}
              </td>
              <td
                class="border-b border-brand-border/80 px-5 py-3.5 text-center text-brand-text-secondary group-hover:bg-[#EEF2F0]"
                dir="ltr"
              >
                {{ contract.start_date }}
              </td>
              <td
                class="border-b border-brand-border/80 px-5 py-3.5 text-center text-brand-text-secondary group-hover:bg-[#EEF2F0]"
                dir="ltr"
              >
                {{ contract.end_date ?? '—' }}
              </td>
              <td
                class="border-b border-brand-border/80 px-5 py-3.5 text-center group-hover:bg-[#EEF2F0]"
              >
                <div class="flex flex-wrap items-center justify-center gap-1.5">
                  <span
                    class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold"
                    :class="contractStatusBadgeClass(contract.status)"
                  >
                    {{ t(`contracts.status.${contract.status}`) }}
                  </span>
                  <span
                    v-if="contract.is_expiring_soon"
                    class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-900 ring-1 ring-amber-200/70"
                  >
                    {{ t('contracts.expiringSoonBadge') }}
                  </span>
                </div>
              </td>
              <td
                class="border-b border-brand-border/80 px-5 py-3.5 text-center text-brand-text-secondary group-hover:bg-[#EEF2F0]"
                dir="ltr"
              >
                {{ formatValue(contract) }}
              </td>
              <td
                class="border-b border-brand-border/80 px-5 py-3.5 text-center group-hover:bg-[#EEF2F0]"
              >
                <div class="inline-flex items-center justify-center gap-0.5">
                  <AppTooltip :text="t('contracts.actions.view')">
                    <RouterLink
                      :to="`/app/contracts/${contract.id}`"
                      class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-brand-text-secondary transition hover:bg-brand-bg"
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
                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-brand-primary-dark transition hover:bg-brand-primary-soft"
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
      <div
        v-if="meta && meta.last_page > 1"
        class="flex items-center justify-between gap-3 border-t border-brand-border bg-[#F7F8F6] px-5 py-3 text-sm"
      >
        <button
          type="button"
          class="inline-flex h-9 items-center gap-1 rounded-lg border border-brand-border bg-brand-surface px-3 font-semibold text-brand-text transition hover:bg-brand-bg disabled:cursor-not-allowed disabled:opacity-40"
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
          class="inline-flex h-9 items-center gap-1 rounded-lg border border-brand-border bg-brand-surface px-3 font-semibold text-brand-text transition hover:bg-brand-bg disabled:cursor-not-allowed disabled:opacity-40"
          :disabled="filters.page >= meta.last_page || isFetching"
          @click="filters.page += 1"
        >
          <span>{{ t('contracts.next') }}</span>
          <ChevronLeft class="h-4 w-4" :stroke-width="2" />
        </button>
      </div>
    </div>

    <ContractFormDrawer
      :open="drawerOpen"
      :editing="editing"
      :form="form"
      :form-error="formError"
      :field-errors="fieldErrors"
      :submitting="isFormSubmitting"
      :category-options="categoryFormOptions"
      :employee-options="employeeFormOptions"
      :org-unit-options="orgUnitFormOptions"
      :counterparty-kind-options="counterpartyKindOptions"
      @close="closeDrawer"
      @submit="submitForm"
      @update:form="assignForm"
    />

    <CategoriesManagerDrawer :open="categoriesOpen" @close="categoriesOpen = false" />
  </div>
</template>
