<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import {
  ArrowRight,
  Building2,
  CalendarRange,
  Coins,
  FileText,
  Pencil,
  Tag,
  UserRound,
} from 'lucide-vue-next'

import EntityDocumentsSection from '@/modules/documents/components/EntityDocumentsSection.vue'
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
import { useContractQuery } from '../queries/useContractQuery'
import type { ContractFormState } from '../types/contracts'
import {
  contractStatusBadgeClass,
  contractStatusDotClass,
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

function isCategoryInactive(): boolean {
  return contract.value?.category?.is_active === false
}

function formatValue(): string {
  if (!contract.value?.value) return '—'
  return `${contract.value.value} ${contract.value.currency}`
}

function formatDateRange(): string {
  if (!contract.value) return '—'
  const start = contract.value.start_date || '—'
  const end = contract.value.end_date || '—'
  return `${start} — ${end}`
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
  <div class="mx-auto min-w-0 max-w-[1200px] space-y-5">
    <div class="flex flex-wrap items-center gap-3">
      <button
        type="button"
        class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-brand-border bg-brand-surface px-3 text-sm font-semibold text-brand-text transition hover:bg-brand-bg"
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
      <section class="rounded-2xl border border-brand-border bg-brand-surface px-4 py-5 sm:px-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
              <p class="font-mono text-xs font-semibold tracking-wide text-brand-text-muted" dir="ltr">
                {{ contract.contract_number }}
              </p>
              <span
                class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold tracking-wide"
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
                class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-900 ring-1 ring-amber-200/70"
              >
                {{ t('contracts.expiringSoonBadge') }}
              </span>
            </div>
            <h2 class="mt-2 break-words text-[1.35rem] font-bold leading-snug text-brand-text sm:text-[1.85rem]">
              {{ contract.title }}
            </h2>
            <p class="mt-2 break-words text-sm text-brand-text-secondary">
              <span class="font-semibold text-brand-text">{{ contract.counterparty_name }}</span>
              <span class="mx-1.5 text-brand-text-muted">·</span>
              {{ t(`contracts.counterpartyKind.${contract.counterparty_kind}`) }}
            </p>
          </div>

          <PermissionGuard v-if="canEdit" permission="contracts.update">
            <button
              type="button"
              class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl border border-brand-border bg-brand-surface px-4 text-sm font-semibold text-brand-primary-dark transition hover:bg-brand-primary-soft sm:h-10 sm:w-auto"
              @click="openEdit"
            >
              <Pencil class="h-4 w-4" />
              {{ t('contracts.actions.edit') }}
            </button>
          </PermissionGuard>
        </div>
      </section>

      <div class="grid min-w-0 gap-5 xl:grid-cols-[minmax(0,1fr)_320px]">
        <div class="order-2 min-w-0 space-y-5 xl:order-1">
          <section class="rounded-2xl border border-brand-border bg-brand-surface">
            <header class="border-b border-brand-border px-5 py-4">
              <h3 class="text-sm font-bold text-brand-text">{{ t('contracts.detailsSummary') }}</h3>
            </header>
            <dl class="grid gap-0 md:grid-cols-2">
              <div class="flex gap-3 border-b border-brand-border/80 px-5 py-4 sm:border-e">
                <span class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-bg text-brand-primary">
                  <Building2 class="h-4 w-4" :stroke-width="1.75" />
                </span>
                <div class="min-w-0">
                  <dt class="text-xs font-semibold text-brand-text-muted">
                    {{ t('contracts.fields.counterpartyName') }}
                  </dt>
                  <dd class="mt-1 text-sm font-semibold text-brand-text">
                    {{ contract.counterparty_name }}
                  </dd>
                  <dd class="mt-0.5 text-xs text-brand-text-secondary">
                    {{ t(`contracts.counterpartyKind.${contract.counterparty_kind}`) }}
                  </dd>
                </div>
              </div>

              <div class="flex gap-3 border-b border-brand-border/80 px-5 py-4">
                <span class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-bg text-brand-primary">
                  <Tag class="h-4 w-4" :stroke-width="1.75" />
                </span>
                <div class="min-w-0">
                  <dt class="text-xs font-semibold text-brand-text-muted">
                    {{ t('contracts.fields.category') }}
                  </dt>
                  <dd class="mt-1 text-sm font-semibold text-brand-text">
                    {{ contract.category?.name ?? '—' }}
                    <span
                      v-if="isCategoryInactive()"
                      class="ms-1 inline-flex rounded-full bg-neutral-100 px-1.5 py-0.5 text-[10px] font-semibold text-neutral-600"
                    >
                      {{ t('contracts.categoryInactive') }}
                    </span>
                  </dd>
                </div>
              </div>

              <div class="flex gap-3 border-b border-brand-border/80 px-5 py-4 sm:border-e">
                <span class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-bg text-brand-primary">
                  <Coins class="h-4 w-4" :stroke-width="1.75" />
                </span>
                <div class="min-w-0">
                  <dt class="text-xs font-semibold text-brand-text-muted">
                    {{ t('contracts.fields.value') }}
                  </dt>
                  <dd class="mt-1 text-sm font-semibold text-brand-text" dir="ltr">
                    {{ formatValue() }}
                  </dd>
                </div>
              </div>

              <div class="flex gap-3 border-b border-brand-border/80 px-5 py-4">
                <span class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-bg text-brand-primary">
                  <CalendarRange class="h-4 w-4" :stroke-width="1.75" />
                </span>
                <div class="min-w-0">
                  <dt class="text-xs font-semibold text-brand-text-muted">
                    {{ t('contracts.fields.startDate') }} / {{ t('contracts.fields.endDate') }}
                  </dt>
                  <dd class="mt-1 text-sm font-semibold text-brand-text" dir="ltr">
                    {{ formatDateRange() }}
                  </dd>
                </div>
              </div>

              <div class="flex gap-3 border-b border-brand-border/80 px-5 py-4 sm:border-e sm:border-b-0">
                <span class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-bg text-brand-primary">
                  <Building2 class="h-4 w-4" :stroke-width="1.75" />
                </span>
                <div class="min-w-0">
                  <dt class="text-xs font-semibold text-brand-text-muted">
                    {{ t('contracts.fields.organizationUnit') }}
                  </dt>
                  <dd class="mt-1 text-sm font-semibold text-brand-text">
                    {{ contract.organization_unit?.name ?? '—' }}
                  </dd>
                </div>
              </div>

              <div class="flex gap-3 px-5 py-4">
                <span class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-bg text-brand-primary">
                  <UserRound class="h-4 w-4" :stroke-width="1.75" />
                </span>
                <div class="min-w-0">
                  <dt class="text-xs font-semibold text-brand-text-muted">
                    {{ t('contracts.fields.employee') }}
                  </dt>
                  <dd class="mt-1 text-sm font-semibold text-brand-text">
                    {{ contract.employee?.full_name ?? '—' }}
                  </dd>
                </div>
              </div>
            </dl>
          </section>

          <section
            v-if="contract.notes"
            class="rounded-2xl border border-brand-border bg-brand-surface"
          >
            <header class="flex items-center gap-2 border-b border-brand-border px-5 py-4">
              <FileText class="h-4 w-4 text-brand-primary" :stroke-width="1.75" />
              <h3 class="text-sm font-bold text-brand-text">{{ t('contracts.fields.notes') }}</h3>
            </header>
            <p class="whitespace-pre-wrap px-5 py-4 text-sm leading-relaxed text-brand-text-secondary">
              {{ contract.notes }}
            </p>
          </section>

          <section class="rounded-2xl border border-brand-border bg-brand-surface">
            <header class="border-b border-brand-border px-5 py-4">
              <h3 class="text-sm font-bold text-brand-text">{{ t('contracts.renewalLineage') }}</h3>
            </header>
            <div class="space-y-2 px-5 py-4 text-sm text-brand-text-secondary">
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
          </section>

          <ContractTimeline :transitions="contract.transitions ?? []" />

          <EntityDocumentsSection
            linkable-type="contract"
            :linkable-id="contract.id"
            :link-label="`${contract.contract_number} — ${contract.title}`"
          />
        </div>

        <aside class="order-1 space-y-5 xl:order-2 xl:sticky xl:top-4 xl:self-start">
          <section class="rounded-2xl border border-brand-border bg-brand-surface">
            <header class="border-b border-brand-border px-5 py-4">
              <h3 class="text-sm font-bold text-brand-text">{{ t('contracts.lifecycleTitle') }}</h3>
              <p class="mt-1 text-xs text-brand-text-secondary">
                {{ t('contracts.lifecycleHint') }}
              </p>
            </header>
            <div class="px-4 py-4">
              <ContractLifecycleActions :contract="contract" @refreshed="onLifecycleRefreshed" />
            </div>
          </section>
        </aside>
      </div>
    </template>

    <ContractFormDrawer
      :open="drawerOpen"
      :editing="contract"
      :form="form"
      :form-error="formError"
      :field-errors="fieldErrors"
      :submitting="isFormSubmitting"
      :org-unit-options="orgUnitFormOptions"
      :counterparty-kind-options="counterpartyKindOptions"
      @close="drawerOpen = false"
      @submit="submitForm"
      @update:form="assignForm"
    />
  </div>
</template>
