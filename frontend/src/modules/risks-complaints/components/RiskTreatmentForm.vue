<script setup lang="ts">
import { computed, reactive, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { Loader2 } from 'lucide-vue-next'

import { listEmployees } from '@/modules/employees/api/employeesApi'
import AppDateInput from '@/shared/components/AppDateInput.vue'
import AppRemoteSelect from '@/shared/components/AppRemoteSelect.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import { employeeSelectOption, toSelectId } from '@/shared/lookups/selectOptions'
import { usePermissions } from '@/shared/composables/usePermissions'

import type { OperationalRisk, RiskTreatmentFormState, TreatmentStatus } from '../types/risks'
import { TREATMENT_STATUSES } from '../types/risks'
import {
  canManageTreatment,
  emptyTreatmentForm,
  validateTreatmentForm,
} from '../validation/riskValidation'

const props = defineProps<{
  risk: OperationalRisk
  submitting: boolean
}>()

const emit = defineEmits<{
  save: [payload: {
    title: string
    plan_body: string
    owner_employee_id: number
    due_at: string | null
    status: TreatmentStatus
  }]
}>()

const { t } = useI18n()
const { can } = usePermissions()

const form = reactive<RiskTreatmentFormState>(emptyTreatmentForm())
const fieldErrors = reactive<Record<string, string>>({})

watch(
  () => props.risk.treatment,
  (treatment) => {
    if (!treatment) {
      Object.assign(form, emptyTreatmentForm())
      return
    }
    Object.assign(form, {
      title: treatment.title,
      plan_body: treatment.plan_body,
      owner_employee_id: treatment.owner_employee_id,
      due_at: treatment.due_at ? treatment.due_at.slice(0, 10) : '',
      status: treatment.status,
    })
  },
  { immediate: true },
)

const editable = computed(
  () => can('risks.manage_treatment') && canManageTreatment(props.risk.status),
)

const statusOptions = computed<AppSelectOption[]>(() =>
  TREATMENT_STATUSES.map((s) => ({ value: s, label: t(`risks.treatmentStatus.${s}`) })),
)

const emptyOwner = computed<AppSelectOption>(() => ({
  value: '',
  label: t('risks.fields.noOwner'),
}))

const selectedOwner = computed(() =>
  props.risk.treatment?.owner_employee
    ? employeeSelectOption(props.risk.treatment.owner_employee)
    : props.risk.owner_employee
      ? employeeSelectOption(props.risk.owner_employee)
      : null,
)

const fetchActiveEmployees = (params: { search?: string; page: number; per_page: number }) =>
  listEmployees({ ...params, status: 'active' })

function submit(): void {
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  const local = validateTreatmentForm(form)
  Object.assign(fieldErrors, local)
  if (Object.keys(local).length) return
  emit('save', {
    title: form.title.trim(),
    plan_body: form.plan_body.trim(),
    owner_employee_id: Number(form.owner_employee_id),
    due_at: form.due_at || null,
    status: form.status,
  })
}
</script>

<template>
  <section class="space-y-4 rounded-2xl border border-brand-border bg-brand-surface p-4" data-testid="risk-treatment-form">
    <h3 class="text-base font-bold text-brand-text">{{ t('risks.tabs.treatment') }}</h3>

    <label class="block">
      <span class="mb-1.5 block text-sm font-semibold">{{ t('risks.fields.treatmentTitle') }}</span>
      <input
        v-model="form.title"
        class="h-11 w-full rounded-xl border border-brand-border px-3 text-sm outline-none focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15"
        :disabled="!editable || submitting"
      />
      <p v-if="fieldErrors.title" class="mt-1 text-xs text-red-600">
        {{ t(`risks.validation.${fieldErrors.title}`) }}
      </p>
    </label>

    <label class="block">
      <span class="mb-1.5 block text-sm font-semibold">{{ t('risks.fields.planBody') }}</span>
      <textarea
        v-model="form.plan_body"
        rows="4"
        class="w-full rounded-xl border border-brand-border p-3 text-sm outline-none focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15"
        :disabled="!editable || submitting"
      />
      <p v-if="fieldErrors.plan_body" class="mt-1 text-xs text-red-600">
        {{ t(`risks.validation.${fieldErrors.plan_body}`) }}
      </p>
    </label>

    <label class="block">
      <span class="mb-1.5 block text-sm font-semibold">{{ t('risks.fields.owner') }}</span>
      <AppRemoteSelect
        :model-value="form.owner_employee_id"
        query-key="employees-active"
        :fetcher="fetchActiveEmployees"
        :map-option="employeeSelectOption"
        :empty-option="emptyOwner"
        :selected-option="selectedOwner"
        :placeholder="t('risks.fields.owner')"
        :disabled="!editable || submitting"
        @update:model-value="form.owner_employee_id = toSelectId($event)"
      />
      <p v-if="fieldErrors.owner_employee_id" class="mt-1 text-xs text-red-600">
        {{ t(`risks.validation.${fieldErrors.owner_employee_id}`) }}
      </p>
    </label>

    <div class="grid gap-3 sm:grid-cols-2">
      <label class="block">
        <span class="mb-1.5 block text-sm font-semibold">{{ t('risks.fields.dueAt') }}</span>
        <AppDateInput
          :model-value="form.due_at"
          :disabled="!editable || submitting"
          @update:model-value="form.due_at = $event"
        />
      </label>
      <label class="block">
        <span class="mb-1.5 block text-sm font-semibold">{{ t('risks.fields.treatmentStatus') }}</span>
        <AppSelect
          :model-value="form.status"
          :options="statusOptions"
          :disabled="!editable || submitting"
          @update:model-value="form.status = String($event) as TreatmentStatus"
        />
      </label>
    </div>

    <button
      v-if="editable"
      type="button"
      class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white disabled:opacity-50"
      :disabled="submitting"
      data-testid="risk-treatment-save"
      @click="submit"
    >
      <Loader2 v-if="submitting" class="h-4 w-4 animate-spin" />
      {{ t('risks.actions.saveTreatment') }}
    </button>
  </section>
</template>
