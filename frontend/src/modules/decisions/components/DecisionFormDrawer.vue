<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { listEmployees } from '@/modules/employees/api/employeesApi'
import AppDateInput from '@/shared/components/AppDateInput.vue'
import AppFormDrawer from '@/shared/components/AppFormDrawer.vue'
import { useCaptureOnOpen, useFormDirty } from '@/shared/composables/useFormDirty'
import AppRemoteSelect from '@/shared/components/AppRemoteSelect.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import { employeeSelectOption, toSelectId } from '@/shared/lookups/selectOptions'
import type { Decision, DecisionFormState } from '../types/decisions'

const props = defineProps<{
  open: boolean
  editing: Decision | null
  form: DecisionFormState
  formError: string
  fieldErrors: Record<string, string>
  submitting: boolean
  orgUnitOptions: AppSelectOption[]
}>()
const emit = defineEmits<{ close: []; submit: []; 'update:form': [DecisionFormState] }>()
const { t } = useI18n()
const isEdit = computed(() => props.editing != null)
const patch = (part: Partial<DecisionFormState>) => emit('update:form', { ...props.form, ...part })
const fetchActiveEmployees = (params: { search?: string; page: number; per_page: number }) =>
  listEmployees({ ...params, status: 'active' })
const emptyIssuer = computed<AppSelectOption>(() => ({
  value: '',
  label: t('decisions.noIssuer'),
}))
const emptyResponsible = computed<AppSelectOption>(() => ({
  value: '',
  label: t('decisions.noResponsible'),
}))
const selectedIssuedBy = computed(() =>
  props.editing?.issued_by_employee ? employeeSelectOption(props.editing.issued_by_employee) : null,
)
const selectedResponsible = computed(() =>
  props.editing?.responsible_employee ? employeeSelectOption(props.editing.responsible_employee) : null,
)

const { dirty, capture, reset: resetDirty } = useFormDirty(() => props.form)
useCaptureOnOpen(() => props.open, capture, { onClose: () => resetDirty() })

function onOpenUpdate(value: boolean): void {
  if (!value) emit('close')
}
</script>
<template>
  <AppFormDrawer
    :open="open"
    :title="t(isEdit ? 'decisions.editTitle' : 'decisions.createTitle')"
    :description="t(isEdit ? 'decisions.editSubtitle' : 'decisions.createSubtitle')"
    size="md"
    :dirty="dirty"
    :submitting="submitting"
    :cancel-label="t('decisions.cancel')"
    :submit-label="t(isEdit ? 'decisions.editCta' : 'decisions.createCta')"
    @update:open="onOpenUpdate"
    @close="emit('close')"
    @submit="emit('submit')"
  >
    <section class="space-y-3">
      <h4 class="font-bold">{{ t('decisions.sections.basics') }}</h4>
      <label class="block">
        <span>{{ t('decisions.fields.decisionNumber') }}</span>
        <input
          readonly
          :value="editing?.decision_number ?? t('decisions.numberPlaceholder')"
          class="mt-1 h-11 w-full rounded-xl border border-brand-border bg-brand-bg px-3"
        />
      </label>
      <label class="block">
        <span>{{ t('decisions.fields.title') }}</span>
        <input
          :value="form.title"
          required
          class="mt-1 h-11 w-full rounded-xl border border-brand-border px-3"
          @input="patch({ title: ($event.target as HTMLInputElement).value })"
        />
        <p v-if="fieldErrors.title" class="text-xs text-red-600">
          {{ t(`decisions.validation.${fieldErrors.title}`) }}
        </p>
      </label>
      <label class="block">
        <span>{{ t('decisions.fields.body') }}</span>
        <textarea
          :value="form.body"
          required
          rows="4"
          class="mt-1 w-full rounded-xl border border-brand-border p-3"
          @input="patch({ body: ($event.target as HTMLTextAreaElement).value })"
        />
        <p v-if="fieldErrors.body" class="text-xs text-red-600">
          {{ t(`decisions.validation.${fieldErrors.body}`) }}
        </p>
      </label>
    </section>
    <section>
      <h4 class="font-bold">{{ t('decisions.sections.source') }}</h4>
      <p class="mt-2 rounded-xl bg-brand-bg p-3 text-sm text-brand-text-secondary">
        {{ editing?.source_recommendation ? editing.source_recommendation.title : t('decisions.standaloneSource') }}
      </p>
    </section>
    <section class="space-y-3">
      <h4 class="font-bold">{{ t('decisions.sections.responsibility') }}</h4>
      <label class="block">
        <span class="mb-1.5 block text-sm font-semibold text-brand-text">{{ t('decisions.fields.organizationUnit') }}</span>
        <AppSelect
          :model-value="form.organization_unit_id"
          :options="orgUnitOptions"
          searchable
          @update:model-value="patch({ organization_unit_id: toSelectId($event) })"
        />
      </label>
      <label class="block">
        <span class="mb-1.5 block text-sm font-semibold text-brand-text">{{ t('decisions.fields.issuedBy') }}</span>
        <AppRemoteSelect
          :model-value="form.issued_by_employee_id"
          query-key="employees-active"
          :fetcher="fetchActiveEmployees"
          :map-option="employeeSelectOption"
          :empty-option="emptyIssuer"
          :selected-option="selectedIssuedBy"
          :placeholder="t('decisions.fields.issuedBy')"
          :enabled="open"
          @update:model-value="patch({ issued_by_employee_id: toSelectId($event) })"
        />
      </label>
      <label class="block">
        <span class="mb-1.5 block text-sm font-semibold text-brand-text">{{ t('decisions.fields.responsible') }}</span>
        <AppRemoteSelect
          :model-value="form.responsible_employee_id"
          query-key="employees-active"
          :fetcher="fetchActiveEmployees"
          :map-option="employeeSelectOption"
          :empty-option="emptyResponsible"
          :selected-option="selectedResponsible"
          :placeholder="t('decisions.fields.responsible')"
          :enabled="open"
          @update:model-value="patch({ responsible_employee_id: toSelectId($event) })"
        />
      </label>
    </section>
    <section class="space-y-3">
      <h4 class="font-bold">{{ t('decisions.sections.dates') }}</h4>
      <label>
        <span>{{ t('decisions.fields.effectiveDate') }}</span>
        <AppDateInput
          class="mt-1"
          :model-value="form.effective_date"
          @update:model-value="patch({ effective_date: $event })"
        />
      </label>
      <label>
        <span>{{ t('decisions.fields.dueDate') }}</span>
        <AppDateInput
          class="mt-1"
          :model-value="form.due_date"
          @update:model-value="patch({ due_date: $event })"
        />
      </label>
    </section>
    <section>
      <h4 class="font-bold">{{ t('decisions.sections.notes') }}</h4>
      <textarea
        :value="form.notes"
        rows="3"
        class="mt-2 w-full rounded-xl border border-brand-border p-3"
        @input="patch({ notes: ($event.target as HTMLTextAreaElement).value })"
      />
    </section>
    <p v-if="formError" class="text-red-700">{{ formError }}</p>
  </AppFormDrawer>
</template>
