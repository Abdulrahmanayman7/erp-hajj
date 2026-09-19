<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRouter } from 'vue-router'
import { ChevronLeft, ChevronRight, Eye, Pencil, Plus, Search } from 'lucide-vue-next'

import { listEmployees } from '@/modules/employees/api/employeesApi'
import { useOrganizationUnitsFlatQuery } from '@/modules/organization/queries/useOrganizationUnitsQuery'
import AppMobileFilters from '@/shared/components/AppMobileFilters.vue'
import AppNumberInput from '@/shared/components/AppNumberInput.vue'
import AppPageHeader from '@/shared/components/AppPageHeader.vue'
import AppRemoteSelect from '@/shared/components/AppRemoteSelect.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import AppDateInput from '@/shared/components/AppDateInput.vue'
import { employeeSelectOption, toSelectId } from '@/shared/lookups/selectOptions'
import AppTooltip from '@/shared/components/AppTooltip.vue'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { usePermissions } from '@/shared/composables/usePermissions'
import { useToast } from '@/shared/composables/useToast'
import { useDebouncedRef } from '@/shared/composables/useDebouncedRef'

import DecisionFormDrawer from '../components/DecisionFormDrawer.vue'
import {
  useCreateDecisionMutation,
  useUpdateDecisionMutation,
} from '../mutations/useDecisionMutations'
import { useDecisionsQuery } from '../queries/useDecisionsQuery'
import type {
  Decision,
  DecisionFormState,
  DecisionStatus,
  ListDecisionsParams,
} from '../types/decisions'
import {
  DECISION_STATUSES,
  decisionStatusBadgeClass,
  decisionStatusDotClass,
  resolveDecisionsListState,
  validateDecisionForm,
} from '../validation/decisionValidation'

const { t } = useI18n()
const router = useRouter()
const { can } = usePermissions()
const toast = useToast()

const focusedRowIndex = ref(-1)

const filters = reactive({
  search: '',
  status: 'all' as DecisionStatus | 'all',
  organization_unit_id: '' as number | '',
  responsible_employee_id: '' as number | '',
  has_source_recommendation: '' as '' | '1' | '0',
  meeting_id: '' as number | '',
  effective_date_from: '',
  effective_date_to: '',
  due_date_from: '',
  due_date_to: '',
  page: 1,
  per_page: 15,
  sort: 'decision_number',
  direction: 'desc',
})

const committedSearch = useDebouncedRef(() => filters.search)
const params = computed<ListDecisionsParams>(() => ({
  ...filters,
  search: committedSearch.value,
  status: filters.status === 'all' ? '' : filters.status,
  has_source_recommendation:
    filters.has_source_recommendation === ''
      ? ''
      : filters.has_source_recommendation === '1',
}))

const { data, isLoading, isError, refetch, isFetching } = useDecisionsQuery(params)
const decisions = computed(() => data.value?.data ?? [])
const meta = computed(() => data.value?.meta)
const listState = computed(() =>
  resolveDecisionsListState({
    isLoading: isLoading.value,
    isError: isError.value,
    count: decisions.value.length,
  }),
)

const { data: orgs } = useOrganizationUnitsFlatQuery({ status: 'active' })
const fetchActiveEmployees = (params: { search?: string; page: number; per_page: number }) =>
  listEmployees({ ...params, status: 'active' })

const orgOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('decisions.filters.allOrgUnits') },
  ...(orgs.value?.data ?? []).map((x) => ({ value: x.id, label: x.name, hint: x.code })),
])
const orgFormOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('decisions.noOrgUnit') },
  ...(orgs.value?.data ?? []).map((x) => ({ value: x.id, label: x.name, hint: x.code })),
])

const emptyEmployee = computed<AppSelectOption>(() => ({ value: '', label: t('decisions.noEmployee') }))

const statusOptions = computed<AppSelectOption[]>(() => [
  { value: 'all', label: t('decisions.filters.allStatuses') },
  ...DECISION_STATUSES.map((x) => ({ value: x, label: t(`decisions.status.${x}`) })),
])

const sourceOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('decisions.filters.allSources') },
  { value: '1', label: t('decisions.filters.withSource') },
  { value: '0', label: t('decisions.filters.standalone') },
])

const create = useCreateDecisionMutation()
const update = useUpdateDecisionMutation()
const drawerOpen = ref(false)
const editing = ref<Decision | null>(null)
const error = ref('')
const fieldErrors = reactive<Record<string, string>>({})
const form = reactive<DecisionFormState>({
  title: '',
  body: '',
  notes: '',
  organization_unit_id: '',
  issued_by_employee_id: '',
  responsible_employee_id: '',
  effective_date: '',
  due_date: '',
})

const isFormSubmitting = computed(() => create.isPending.value || update.isPending.value)

const activeFilterCount = computed(() => {
  let count = 0
  if (filters.status !== 'all') count += 1
  if (filters.organization_unit_id !== '') count += 1
  if (filters.responsible_employee_id !== '') count += 1
  if (filters.has_source_recommendation !== '') count += 1
  if (filters.meeting_id !== '') count += 1
  if (filters.effective_date_from) count += 1
  if (filters.effective_date_to) count += 1
  if (filters.due_date_from) count += 1
  if (filters.due_date_to) count += 1
  return count
})

function resetFilters(): void {
  filters.status = 'all'
  filters.organization_unit_id = ''
  filters.responsible_employee_id = ''
  filters.has_source_recommendation = ''
  filters.meeting_id = ''
  filters.effective_date_from = ''
  filters.effective_date_to = ''
  filters.due_date_from = ''
  filters.due_date_to = ''
}

watch(
  () => [
    committedSearch.value,
    filters.status,
    filters.organization_unit_id,
    filters.responsible_employee_id,
    filters.has_source_recommendation,
    filters.meeting_id,
    filters.effective_date_from,
    filters.effective_date_to,
    filters.due_date_from,
    filters.due_date_to,
  ],
  () => {
    filters.page = 1
  },
)

watch(
  decisions,
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
  if (decisions.value.length === 0) {
    focusedRowIndex.value = -1
    return
  }
  focusedRowIndex.value = Math.min(Math.max(index, 0), decisions.value.length - 1)
}

function openFocusedDecision(): void {
  const decision = decisions.value[focusedRowIndex.value]
  if (!decision) return
  void router.push(`/app/decisions/${decision.id}`)
}

function onTableKeydown(event: KeyboardEvent): void {
  if (drawerOpen.value || decisions.value.length === 0) return

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
    focusRow(decisions.value.length - 1)
    return
  }

  if (event.key === 'Enter' && focusedRowIndex.value >= 0) {
    event.preventDefault()
    openFocusedDecision()
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

function assignForm(v: DecisionFormState): void {
  Object.assign(form, v)
}

function openCreate(): void {
  editing.value = null
  assignForm({
    title: '',
    body: '',
    notes: '',
    organization_unit_id: '',
    issued_by_employee_id: '',
    responsible_employee_id: '',
    effective_date: '',
    due_date: '',
  })
  error.value = ''
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  drawerOpen.value = true
}

function openEdit(v: Decision): void {
  if (v.status !== 'draft' || !can('decisions.update')) return
  editing.value = v
  assignForm({
    title: v.title,
    body: v.body,
    notes: v.notes ?? '',
    organization_unit_id: v.organization_unit_id ?? '',
    issued_by_employee_id: v.issued_by_employee_id ?? '',
    responsible_employee_id: v.responsible_employee_id ?? '',
    effective_date: v.effective_date ?? '',
    due_date: v.due_date ?? '',
  })
  error.value = ''
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  drawerOpen.value = true
}

async function save(): Promise<void> {
  Object.keys(fieldErrors).forEach((x) => delete fieldErrors[x])
  Object.assign(fieldErrors, validateDecisionForm(form))
  if (Object.keys(fieldErrors).length) return

  const payload = {
    title: form.title.trim(),
    body: form.body.trim(),
    notes: form.notes.trim() || null,
    organization_unit_id: form.organization_unit_id || null,
    issued_by_employee_id: form.issued_by_employee_id || null,
    responsible_employee_id: form.responsible_employee_id || null,
    effective_date: form.effective_date || null,
    due_date: form.due_date || null,
  }

  try {
    if (editing.value) {
      await update.mutateAsync({ id: editing.value.id, payload })
      toast.success(t('decisions.toasts.updated'))
    } else {
      await create.mutateAsync(payload)
      toast.success(t('decisions.toasts.created'))
    }
    drawerOpen.value = false
  } catch {
    error.value = t('decisions.errors.generic')
  }
}
</script>

<template>
  <div class="space-y-6">
    <AppPageHeader
      :title="t('decisions.title')"
      :subtitle="t('decisions.subtitle')"
      :meta="meta ? t('decisions.total', { count: meta.total }) : undefined"
    >
      <template #actions>
        <PermissionGuard permission="decisions.create">
          <button type="button" class="app-btn-primary w-full sm:w-auto" @click="openCreate">
            <Plus class="h-4 w-4" :stroke-width="2.25" />
            <span>{{ t('decisions.add') }}</span>
          </button>
        </PermissionGuard>
      </template>
    </AppPageHeader>

    <AppMobileFilters
      v-model:search="filters.search"
      :search-placeholder="t('decisions.searchPlaceholder')"
      :active-count="activeFilterCount"
      @reset="resetFilters"
    >
      <template #desktop>
        <div class="app-surface-flat space-y-3 p-3.5">
          <div class="flex flex-wrap items-center gap-2.5">
            <div class="relative min-w-[16rem] flex-[1_1_16rem]">
              <Search
                class="pointer-events-none absolute inset-s-3 top-1/2 h-4 w-4 -translate-y-1/2 text-brand-text-muted"
                :stroke-width="1.75"
                aria-hidden="true"
              />
              <input
                v-model="filters.search"
                type="search"
                class="app-input app-input--search"
                :placeholder="t('decisions.searchPlaceholder')"
              />
            </div>
            <div class="min-w-[11rem] flex-[0_1_13rem]">
              <AppSelect v-model="filters.status" :options="statusOptions" />
            </div>
            <div class="min-w-[11rem] flex-[0_1_14rem]">
              <AppSelect v-model="filters.organization_unit_id" :options="orgOptions" searchable />
            </div>
            <div class="min-w-[11rem] flex-[0_1_14rem]">
              <AppRemoteSelect
                :model-value="filters.responsible_employee_id"
                query-key="employees-active"
                :fetcher="fetchActiveEmployees"
                :map-option="employeeSelectOption"
                :empty-option="emptyEmployee"
                @update:model-value="filters.responsible_employee_id = toSelectId($event)"
              />
            </div>
            <div class="min-w-[11rem] flex-[0_1_13rem]">
              <AppSelect v-model="filters.has_source_recommendation" :options="sourceOptions" />
            </div>
          </div>

          <div
            class="flex flex-wrap items-end gap-x-5 gap-y-3 border-t border-[var(--border-soft)] pt-3"
          >
            <div class="flex min-w-0 flex-wrap items-center gap-2">
              <span class="text-xs font-semibold text-brand-text-secondary">
                {{ t('decisions.columns.effectiveDate') }}
              </span>
              <div class="w-[11.5rem] shrink-0">
                <AppDateInput
                  v-model="filters.effective_date_from"
                  :placeholder="t('decisions.filters.dateFrom')"
                  :aria-label="`${t('decisions.columns.effectiveDate')} — ${t('decisions.filters.dateFrom')}`"
                />
              </div>
              <div class="w-[11.5rem] shrink-0">
                <AppDateInput
                  v-model="filters.effective_date_to"
                  :placeholder="t('decisions.filters.dateTo')"
                  :aria-label="`${t('decisions.columns.effectiveDate')} — ${t('decisions.filters.dateTo')}`"
                />
              </div>
            </div>
            <div class="flex min-w-0 flex-wrap items-center gap-2">
              <span class="text-xs font-semibold text-brand-text-secondary">
                {{ t('decisions.columns.dueDate') }}
              </span>
              <div class="w-[11.5rem] shrink-0">
                <AppDateInput
                  v-model="filters.due_date_from"
                  :placeholder="t('decisions.filters.dateFrom')"
                  :aria-label="`${t('decisions.columns.dueDate')} — ${t('decisions.filters.dateFrom')}`"
                />
              </div>
              <div class="w-[11.5rem] shrink-0">
                <AppDateInput
                  v-model="filters.due_date_to"
                  :placeholder="t('decisions.filters.dateTo')"
                  :aria-label="`${t('decisions.columns.dueDate')} — ${t('decisions.filters.dateTo')}`"
                />
              </div>
            </div>
            <div class="w-[11.5rem] shrink-0">
              <label class="sr-only" for="decisions-filter-meeting-id">
                {{ t('decisions.filters.meetingId') }}
              </label>
              <AppNumberInput
                id="decisions-filter-meeting-id"
                :model-value="filters.meeting_id"
                integer
                min="1"
                class="app-input"
                :placeholder="t('decisions.filters.meetingId')"
                @update:model-value="filters.meeting_id = $event === '' ? '' : Number($event)"
              />
            </div>
          </div>
        </div>
      </template>
      <template #filters>
        <div class="space-y-3">
          <AppSelect v-model="filters.status" :options="statusOptions" />
          <AppSelect v-model="filters.organization_unit_id" :options="orgOptions" searchable />
          <AppRemoteSelect
            :model-value="filters.responsible_employee_id"
            query-key="employees-active"
            :fetcher="fetchActiveEmployees"
            :map-option="employeeSelectOption"
            :empty-option="emptyEmployee"
            @update:model-value="filters.responsible_employee_id = toSelectId($event)"
          />
          <AppSelect v-model="filters.has_source_recommendation" :options="sourceOptions" />
          <label class="block">
            <span class="mb-1.5 block text-sm font-semibold text-brand-text">{{ t('decisions.filters.meetingId') }}</span>
            <AppNumberInput
              :model-value="filters.meeting_id"
              integer
              min="1"
              class="app-input"
              :placeholder="t('decisions.filters.meetingId')"
              @update:model-value="filters.meeting_id = $event === '' ? '' : Number($event)"
            />
          </label>
          <fieldset class="space-y-2">
            <legend class="text-sm font-semibold text-brand-text">{{ t('decisions.columns.effectiveDate') }}</legend>
            <div class="grid grid-cols-2 gap-2">
              <AppDateInput
                v-model="filters.effective_date_from"
                :placeholder="t('decisions.filters.dateFrom')"
              />
              <AppDateInput
                v-model="filters.effective_date_to"
                :placeholder="t('decisions.filters.dateTo')"
              />
            </div>
          </fieldset>
          <fieldset class="space-y-2">
            <legend class="text-sm font-semibold text-brand-text">{{ t('decisions.columns.dueDate') }}</legend>
            <div class="grid grid-cols-2 gap-2">
              <AppDateInput
                v-model="filters.due_date_from"
                :placeholder="t('decisions.filters.dateFrom')"
              />
              <AppDateInput
                v-model="filters.due_date_to"
                :placeholder="t('decisions.filters.dateTo')"
              />
            </div>
          </fieldset>
        </div>
      </template>
    </AppMobileFilters>

    <div
      v-if="listState === 'loading'"
      class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center text-sm text-brand-text-muted"
    >
      {{ t('decisions.loading') }}
    </div>
    <div
      v-else-if="listState === 'error'"
      class="rounded-2xl border border-red-200 bg-red-50 p-10 text-center"
    >
      <p class="text-sm text-red-700">{{ t('decisions.errors.load') }}</p>
      <button
        type="button"
        class="mt-3 text-sm font-semibold text-brand-primary-dark underline"
        @click="() => refetch()"
      >
        {{ t('decisions.retry') }}
      </button>
    </div>
    <div
      v-else-if="listState === 'empty'"
      class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center"
    >
      <p class="text-sm text-brand-text-muted">{{ t('decisions.empty') }}</p>
      <PermissionGuard permission="decisions.create">
        <button
          type="button"
          class="mt-4 inline-flex h-10 items-center gap-2 rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white"
          @click="openCreate"
        >
          <Plus class="h-4 w-4" />
          {{ t('decisions.add') }}
        </button>
      </PermissionGuard>
    </div>
    <template v-else>
      <div
        ref="tableRoot"
        class="hidden overflow-hidden rounded-2xl border border-brand-border bg-brand-surface shadow-[0_1px_2px_rgba(23,32,29,0.03)] outline-none focus-visible:ring-2 focus-visible:ring-brand-primary/25 lg:block"
        tabindex="0"
        role="grid"
        :aria-rowcount="decisions.length"
        :aria-label="t('decisions.title')"
        @keydown="onTableKeydown"
      >
      <div class="overflow-x-auto">
        <table class="min-w-full border-separate border-spacing-0 text-sm">
          <thead>
            <tr class="bg-[#F4F6F5]">
              <th
                class="whitespace-nowrap border-b border-s-[3px] border-brand-border border-s-transparent px-5 py-3.5 text-start text-xs font-bold tracking-wide text-brand-text"
              >
                {{ t('decisions.columns.number') }}
              </th>
              <th
                class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-start text-xs font-bold tracking-wide text-brand-text"
              >
                {{ t('decisions.columns.title') }}
              </th>
              <th
                class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-center text-xs font-bold tracking-wide text-brand-text"
              >
                {{ t('decisions.columns.source') }}
              </th>
              <th
                class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-center text-xs font-bold tracking-wide text-brand-text"
              >
                {{ t('decisions.columns.responsible') }}
              </th>
              <th
                class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-center text-xs font-bold tracking-wide text-brand-text"
              >
                {{ t('decisions.columns.organizationUnit') }}
              </th>
              <th
                class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-center text-xs font-bold tracking-wide text-brand-text"
              >
                {{ t('decisions.columns.effectiveDate') }}
              </th>
              <th
                class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-center text-xs font-bold tracking-wide text-brand-text"
              >
                {{ t('decisions.columns.dueDate') }}
              </th>
              <th
                class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-center text-xs font-bold tracking-wide text-brand-text"
              >
                {{ t('decisions.columns.status') }}
              </th>
              <th
                class="w-28 whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-center text-xs font-bold tracking-wide text-brand-text"
              >
                {{ t('decisions.columns.actions') }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="(decision, index) in decisions"
              :key="decision.id"
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
                  :to="`/app/decisions/${decision.id}`"
                  class="inline-flex items-center gap-1.5 rounded-lg border border-brand-border bg-brand-bg px-2.5 py-1 font-mono text-[12px] font-bold tracking-wide text-brand-primary-dark shadow-[0_1px_0_rgba(23,32,29,0.04)] transition group-hover:border-brand-primary/30 group-hover:bg-brand-surface hover:border-brand-primary/35 hover:bg-brand-primary-soft hover:text-brand-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-primary/25"
                  :title="t('decisions.actions.view')"
                  dir="ltr"
                >
                  {{ decision.decision_number }}
                </RouterLink>
              </td>
              <td
                class="border-b border-brand-border/80 px-5 py-3.5 font-semibold text-brand-text transition-colors duration-150 group-hover:bg-[#EDF6F1]"
              >
                <RouterLink
                  :to="`/app/decisions/${decision.id}`"
                  class="transition group-hover:text-brand-primary-dark hover:underline hover:underline-offset-2"
                >
                  {{ decision.title }}
                </RouterLink>
              </td>
              <td
                class="border-b border-brand-border/80 px-5 py-3.5 text-center text-brand-text transition-colors duration-150 group-hover:bg-[#EDF6F1]"
              >
                {{ decision.source_recommendation?.title ?? t('decisions.standaloneSource') }}
              </td>
              <td
                class="border-b border-brand-border/80 px-5 py-3.5 text-center text-brand-text transition-colors duration-150 group-hover:bg-[#EDF6F1]"
              >
                {{ decision.responsible_employee?.full_name ?? '—' }}
              </td>
              <td
                class="border-b border-brand-border/80 px-5 py-3.5 text-center text-brand-text transition-colors duration-150 group-hover:bg-[#EDF6F1]"
              >
                {{ decision.organization_unit?.name ?? '—' }}
              </td>
              <td
                class="border-b border-brand-border/80 px-5 py-3.5 text-center text-brand-text transition-colors duration-150 group-hover:bg-[#EDF6F1]"
                dir="ltr"
              >
                {{ decision.effective_date ?? '—' }}
              </td>
              <td
                class="border-b border-brand-border/80 px-5 py-3.5 text-center text-brand-text transition-colors duration-150 group-hover:bg-[#EDF6F1]"
                dir="ltr"
              >
                {{ decision.due_date ?? '—' }}
              </td>
              <td
                class="border-b border-brand-border/80 px-5 py-3.5 text-center transition-colors duration-150 group-hover:bg-[#EDF6F1]"
              >
                <span
                  class="inline-flex min-w-[7.25rem] items-center justify-center gap-1.5 rounded-full px-3 py-1 text-[11px] font-bold tracking-wide shadow-sm"
                  :class="decisionStatusBadgeClass(decision.status)"
                >
                  <span
                    class="h-1.5 w-1.5 shrink-0 rounded-full"
                    :class="decisionStatusDotClass(decision.status)"
                    aria-hidden="true"
                  />
                  {{ t(`decisions.status.${decision.status}`) }}
                </span>
              </td>
              <td
                class="border-b border-brand-border/80 px-5 py-3.5 text-center transition-colors duration-150 group-hover:bg-[#EDF6F1]"
              >
                <div
                  class="inline-flex items-center justify-center gap-0.5 opacity-70 transition group-hover:opacity-100"
                >
                  <AppTooltip :text="t('decisions.actions.view')">
                    <RouterLink
                      :to="`/app/decisions/${decision.id}`"
                      class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-brand-text transition hover:bg-brand-surface hover:text-brand-primary-dark"
                      :aria-label="t('decisions.actions.view')"
                    >
                      <Eye class="h-4 w-4" :stroke-width="2" />
                    </RouterLink>
                  </AppTooltip>
                  <PermissionGuard
                    v-if="decision.status === 'draft'"
                    permission="decisions.update"
                  >
                    <AppTooltip :text="t('decisions.actions.edit')">
                      <button
                        type="button"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-brand-primary-dark transition hover:bg-brand-surface"
                        :aria-label="t('decisions.actions.edit')"
                        @click="openEdit(decision)"
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

      <div class="space-y-3 lg:hidden">
        <RouterLink
          v-for="decision in decisions"
          :key="decision.id"
          :to="`/app/decisions/${decision.id}`"
          class="block rounded-2xl border border-brand-border bg-brand-surface p-4 shadow-[0_1px_2px_rgba(23,32,29,0.03)] transition active:bg-brand-bg"
        >
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <p class="font-mono text-xs font-bold text-brand-primary-dark" dir="ltr">
                {{ decision.decision_number }}
              </p>
              <h3 class="mt-1 font-bold text-brand-text">{{ decision.title }}</h3>
            </div>
            <span
              class="inline-flex shrink-0 items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-bold"
              :class="decisionStatusBadgeClass(decision.status)"
            >
              <span
                class="h-1.5 w-1.5 shrink-0 rounded-full"
                :class="decisionStatusDotClass(decision.status)"
              />
              {{ t(`decisions.status.${decision.status}`) }}
            </span>
          </div>
          <dl class="mt-3 grid grid-cols-2 gap-2 text-xs text-brand-text-secondary">
            <div>
              <dt>{{ t('decisions.columns.responsible') }}</dt>
              <dd class="font-medium text-brand-text">
                {{ decision.responsible_employee?.full_name ?? '—' }}
              </dd>
            </div>
            <div>
              <dt>{{ t('decisions.columns.organizationUnit') }}</dt>
              <dd class="font-medium text-brand-text">
                {{ decision.organization_unit?.name ?? '—' }}
              </dd>
            </div>
            <div>
              <dt>{{ t('decisions.columns.effectiveDate') }}</dt>
              <dd class="font-medium text-brand-text" dir="ltr">{{ decision.effective_date ?? '—' }}</dd>
            </div>
            <div>
              <dt>{{ t('decisions.columns.dueDate') }}</dt>
              <dd class="font-medium text-brand-text" dir="ltr">{{ decision.due_date ?? '—' }}</dd>
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
          <span>{{ t('decisions.prev') }}</span>
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
          <span>{{ t('decisions.next') }}</span>
          <ChevronLeft class="h-4 w-4" :stroke-width="2" />
        </button>
      </div>
    </template>

    <DecisionFormDrawer
      :open="drawerOpen"
      :editing="editing"
      :form="form"
      :form-error="error"
      :field-errors="fieldErrors"
      :submitting="isFormSubmitting"
      :org-unit-options="orgFormOptions"
      @close="drawerOpen = false"
      @submit="save"
      @update:form="assignForm"
    />
  </div>
</template>
