<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { ArrowRight, Pencil } from 'lucide-vue-next'

import { useEmployeesQuery } from '@/modules/employees/queries/useEmployeesQuery'
import { useOrganizationUnitsFlatQuery } from '@/modules/organization/queries/useOrganizationUnitsQuery'
import { ApiError } from '@/shared/api/http'
import type { AppSelectOption } from '@/shared/components/AppSelect.vue'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { usePermissions } from '@/shared/composables/usePermissions'
import { useToast } from '@/shared/composables/useToast'

import ContractFormDrawer from '../components/ContractFormDrawer.vue'
import ContractLifecycleActions from '../components/ContractLifecycleActions.vue'
import ContractTimeline from '../components/ContractTimeline.vue'
import { useUpdateContractMutation } from '../mutations/useContractMutations'
import { useCategoriesQuery } from '../queries/useCategoriesQuery'
import { useContractQuery } from '../queries/useContractQuery'
import type { ContractFormState } from '../types/contracts'
import {
  contractStatusBadgeClass,
  mapContractErrorCode,
  validateContractForm,
} from '../validation/contractValidation'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const { can } = usePermissions()
const toast = useToast()

const contractId = computed(() => {
  const raw = route.params.id
  const value = Number(Array.isArray(raw) ? raw[0] : raw)
  return Number.isFinite(value) ? value : null
})

const { data, isLoading, isError, refetch } = useContractQuery(contractId)
const contract = computed(() => data.value ?? null)

const { data: orgUnitsData } = useOrganizationUnitsFlatQuery({ status: 'active' })
const { data: categoriesData } = useCategoriesQuery({ per_page: 100 })
const { data: activeCategoriesData } = useCategoriesQuery({ is_active: true, per_page: 100 })
const { data: employeesData } = useEmployeesQuery(
  computed(() => ({ status: 'active' as const, per_page: 100 })),
)

const updateMutation = useUpdateContractMutation()
const isFormSubmitting = computed(() => updateMutation.isPending.value)

const drawerOpen = ref(false)
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

const canEdit = computed(
  () => contract.value?.status === 'draft' && can('contracts.update'),
)

const categoryFormOptions = computed<AppSelectOption[]>(() => {
  const active = activeCategoriesData.value?.data ?? []
  const options = active.map((c) => ({
    value: c.id,
    label: c.name,
    hint: c.code ?? undefined,
  }))
  if (contract.value?.category) {
    const currentId = contract.value.category.id
    if (!options.some((o) => o.value === currentId)) {
      options.unshift({
        value: currentId,
        label: contract.value.category.name,
        hint: t('contracts.categoryInactive'),
      })
    }
  }
  return options
})

const orgUnitFormOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('contracts.noOrgUnit') },
  ...(orgUnitsData.value?.data ?? []).map((u) => ({
    value: u.id,
    label: u.name,
    hint: u.code,
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

function isCategoryInactive(): boolean {
  const current = contract.value?.category
  if (!current) return false
  if (current.is_active === false) return true
  const found = (categoriesData.value?.data ?? []).find((c) => c.id === current.id)
  return found ? !found.is_active : false
}

function formatValue(): string {
  if (!contract.value?.value) return '—'
  return `${contract.value.value} ${contract.value.currency}`
}

function openEdit(): void {
  if (!contract.value || contract.value.status !== 'draft') return
  Object.assign(form, {
    title: contract.value.title,
    contract_category_id: contract.value.category?.id ?? '',
    counterparty_name: contract.value.counterparty_name,
    counterparty_kind: contract.value.counterparty_kind,
    employee_id: contract.value.employee?.id ?? '',
    organization_unit_id: contract.value.organization_unit?.id ?? '',
    start_date: contract.value.start_date,
    end_date: contract.value.end_date ?? '',
    value: contract.value.value ?? '',
    currency: contract.value.currency || 'SAR',
    notes: contract.value.notes ?? '',
  })
  formError.value = ''
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  drawerOpen.value = true
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

async function submitForm(): Promise<void> {
  if (!contract.value) return
  formError.value = ''
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
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

  try {
    await updateMutation.mutateAsync({
      id: contract.value.id,
      payload: {
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
      },
    })
    toast.success(t('contracts.toasts.updated'))
    drawerOpen.value = false
    await refetch()
  } catch (error) {
    formError.value = apiMessage(error)
  }
}

function assignForm(next: ContractFormState): void {
  Object.assign(form, next)
}

async function onLifecycleRefreshed(): Promise<void> {
  await refetch()
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-center gap-3">
      <button
        type="button"
        class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-brand-border bg-brand-surface px-3 text-sm font-semibold text-brand-text hover:bg-brand-bg"
        @click="router.push('/app/contracts')"
      >
        <ArrowRight class="h-4 w-4" />
        {{ t('contracts.backToList') }}
      </button>
    </div>

    <div
      v-if="isLoading"
      class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center text-sm text-brand-text-muted"
    >
      {{ t('contracts.loadingDetails') }}
    </div>

    <div
      v-else-if="isError || !contract"
      class="rounded-2xl border border-red-200 bg-red-50 p-10 text-center"
    >
      <p class="text-sm text-red-700">{{ t('contracts.errors.loadDetails') }}</p>
      <button
        type="button"
        class="mt-3 text-sm font-semibold text-brand-primary-dark underline"
        @click="() => refetch()"
      >
        {{ t('contracts.retry') }}
      </button>
    </div>

    <template v-else>
      <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
          <div class="flex flex-wrap items-center gap-2">
            <p class="font-mono text-sm text-brand-text-muted" dir="ltr">
              {{ contract.contract_number }}
            </p>
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
          <h2 class="mt-2 text-[1.75rem] font-bold leading-tight text-brand-text">
            {{ contract.title }}
          </h2>
        </div>
        <PermissionGuard v-if="canEdit" permission="contracts.update">
          <button
            type="button"
            class="inline-flex h-11 items-center gap-2 rounded-xl border border-brand-border bg-brand-surface px-4 text-sm font-semibold text-brand-primary-dark hover:bg-brand-primary-soft"
            @click="openEdit"
          >
            <Pencil class="h-4 w-4" />
            {{ t('contracts.actions.edit') }}
          </button>
        </PermissionGuard>
      </div>

      <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <div class="rounded-2xl border border-brand-border bg-brand-surface p-4">
          <p class="text-xs font-bold text-brand-text-muted">
            {{ t('contracts.fields.counterpartyName') }}
          </p>
          <p class="mt-1.5 text-sm font-semibold text-brand-text">
            {{ contract.counterparty_name }}
          </p>
          <p class="mt-1 text-xs text-brand-text-secondary">
            {{ t(`contracts.counterpartyKind.${contract.counterparty_kind}`) }}
          </p>
        </div>
        <div class="rounded-2xl border border-brand-border bg-brand-surface p-4">
          <p class="text-xs font-bold text-brand-text-muted">
            {{ t('contracts.fields.category') }}
          </p>
          <p class="mt-1.5 text-sm font-semibold text-brand-text">
            {{ contract.category?.name ?? '—' }}
            <span
              v-if="isCategoryInactive()"
              class="ms-1 inline-flex rounded-full bg-neutral-100 px-1.5 py-0.5 text-[10px] font-semibold text-neutral-600"
            >
              {{ t('contracts.categoryInactive') }}
            </span>
          </p>
        </div>
        <div class="rounded-2xl border border-brand-border bg-brand-surface p-4">
          <p class="text-xs font-bold text-brand-text-muted">
            {{ t('contracts.fields.value') }}
          </p>
          <p class="mt-1.5 text-sm font-semibold text-brand-text" dir="ltr">{{ formatValue() }}</p>
        </div>
        <div class="rounded-2xl border border-brand-border bg-brand-surface p-4">
          <p class="text-xs font-bold text-brand-text-muted">
            {{ t('contracts.fields.startDate') }} / {{ t('contracts.fields.endDate') }}
          </p>
          <p class="mt-1.5 text-sm font-semibold text-brand-text" dir="ltr">
            {{ contract.start_date }} — {{ contract.end_date ?? '—' }}
          </p>
        </div>
        <div class="rounded-2xl border border-brand-border bg-brand-surface p-4">
          <p class="text-xs font-bold text-brand-text-muted">
            {{ t('contracts.fields.organizationUnit') }}
          </p>
          <p class="mt-1.5 text-sm font-semibold text-brand-text">
            {{ contract.organization_unit?.name ?? '—' }}
          </p>
        </div>
        <div class="rounded-2xl border border-brand-border bg-brand-surface p-4">
          <p class="text-xs font-bold text-brand-text-muted">
            {{ t('contracts.fields.employee') }}
          </p>
          <p class="mt-1.5 text-sm font-semibold text-brand-text">
            {{ contract.employee?.full_name ?? '—' }}
          </p>
        </div>
      </div>

      <div
        v-if="contract.notes"
        class="rounded-2xl border border-brand-border bg-brand-surface p-4"
      >
        <p class="text-xs font-bold text-brand-text-muted">{{ t('contracts.fields.notes') }}</p>
        <p class="mt-1.5 whitespace-pre-wrap text-sm text-brand-text-secondary">
          {{ contract.notes }}
        </p>
      </div>

      <div class="rounded-2xl border border-brand-border bg-brand-surface p-4">
        <p class="text-xs font-bold text-brand-text-muted">
          {{ t('contracts.renewalLineage') }}
        </p>
        <div class="mt-2 space-y-1.5 text-sm text-brand-text-secondary">
          <p v-if="contract.renewed_from_contract_id">
            {{ t('contracts.renewedFrom') }}:
            <RouterLink
              :to="`/app/contracts/${contract.renewed_from_contract_id}`"
              class="font-semibold text-brand-primary-dark hover:underline"
            >
              #{{ contract.renewed_from_contract_id }}
            </RouterLink>
          </p>
          <p v-if="contract.renewal_child">
            {{ t('contracts.renewalChild') }}:
            <RouterLink
              :to="`/app/contracts/${contract.renewal_child.id}`"
              class="font-semibold text-brand-primary-dark hover:underline"
            >
              {{ contract.renewal_child.contract_number }}
            </RouterLink>
          </p>
          <p
            v-if="!contract.renewed_from_contract_id && !contract.renewal_child"
            class="text-brand-text-muted"
          >
            {{ t('contracts.noRenewalLineage') }}
          </p>
        </div>
      </div>

      <div class="rounded-2xl border border-brand-border bg-brand-surface p-4">
        <p class="mb-3 text-sm font-bold text-brand-text">{{ t('contracts.lifecycleTitle') }}</p>
        <ContractLifecycleActions :contract="contract" @refreshed="onLifecycleRefreshed" />
      </div>

      <ContractTimeline :transitions="contract.transitions ?? []" />

      <div
        class="rounded-2xl border border-dashed border-brand-border bg-[#F7F8F6] p-5 text-sm text-brand-text-muted"
      >
        {{ t('contracts.attachmentsPlaceholder') }}
      </div>
    </template>

    <ContractFormDrawer
      :open="drawerOpen"
      :editing="contract"
      :form="form"
      :form-error="formError"
      :field-errors="fieldErrors"
      :submitting="isFormSubmitting"
      :category-options="categoryFormOptions"
      :employee-options="employeeFormOptions"
      :org-unit-options="orgUnitFormOptions"
      :counterparty-kind-options="counterpartyKindOptions"
      @close="drawerOpen = false"
      @submit="submitForm"
      @update:form="assignForm"
    />
  </div>
</template>
